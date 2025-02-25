<?php

namespace Drupal\pipeline\Plugin\LLMService;

use Aws\Polly\PollyClient;
use Aws\Exception\AwsException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\LLMServiceInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LLMService(
 *   id = "aws_polly",
 *   label = @Translation("AWS Polly Service")
 * )
 */
class AWSPollyService extends PluginBase implements LLMServiceInterface, ContainerFactoryPluginInterface
{
  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new AWSPollyService object.
   */
  public function __construct(
    array                         $configuration,
                                  $plugin_id,
                                  $plugin_definition,
    LoggerChannelFactoryInterface $logger_factory,
    FileSystemInterface           $file_system
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loggerFactory = $logger_factory;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function callLLM(array $config, string $prompt): string
  {
    $maxRetries = 3;
    $retryDelay = 5;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        // Create a Polly client
        $client = new PollyClient([
          'version' => 'latest',
          'region' => $config['parameters']['region'] ?? 'us-west-2',
          'credentials' => [
            'key' => $config['api_key'],
            'secret' => $config['api_secret'] ?? '',
          ],
        ]);

        // Set up parameters for speech synthesis
        $params = [
          'Text' => $prompt,
          'VoiceId' => $config['parameters']['voice_id'] ?? 'Joanna',
          'OutputFormat' => $config['parameters']['output_format'] ?? 'mp3',
          'SampleRate' => $config['parameters']['sample_rate'] ?? '22050',
          'Engine' => $config['parameters']['engine'] ?? 'standard',
        ];

        // Synthesize speech
        $result = $client->synthesizeSpeech($params);

        // Save the audio file
        $directory = 'private://pipeline/audio/' . date('Y-m');
        if (!\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
          throw new \Exception('Failed to create directory: ' . $directory);
        }

        $filename = uniqid('polly_', true) . '.mp3';
        $uri = $directory . '/' . $filename;

        $file = \Drupal::service('file.repository')->writeData(
          (string)$result->get('AudioStream')->getContents(),
          $uri,
          FileExists::Replace
        );

        if (!$file) {
          throw new \Exception('Failed to save audio file');
        }

        $file->setPermanent();
        $file->save();

        return json_encode([
          'file_id' => $file->id(),
          'uri' => $file->getFileUri(),
          'url' => $file->createFileUrl(),
          'mime_type' => 'audio/mpeg',
          'filename' => $filename,
          'size' => $file->getSize(),
          'timestamp' => \Drupal::time()->getCurrentTime(),
        ]);
      } catch (AwsException $e) {
        $errorDetails = $this->extractErrorDetails($e);

        if ($attempt === $maxRetries) {
          $this->loggerFactory->get('pipeline')->error('AWS Polly error after @attempts attempts: @details', [
            '@attempts' => $maxRetries,
            '@details' => json_encode($errorDetails),
          ]);

          throw new \Exception(sprintf(
            'Failed to call AWS Polly after %d attempts: %s',
            $maxRetries,
            $errorDetails['message']
          ));
        }

        $this->loggerFactory->get('pipeline')->warning('AWS Polly attempt @attempt failed: @message. Retrying in @delay seconds...', [
          '@attempt' => $attempt,
          '@message' => $errorDetails['message'],
          '@delay' => $retryDelay,
        ]);

        sleep($retryDelay);
      } catch (\Exception $e) {
        $this->loggerFactory->get('pipeline')->error('AWS Polly error: @error', ['@error' => $e->getMessage()]);
        throw $e;
      }
    }

    throw new \Exception('Failed to call AWS Polly after exhausting all retry attempts.');
  }

  /**
   * Extracts error details from AWS exception.
   */
  protected function extractErrorDetails(AwsException $e): array
  {
    return [
      'code' => $e->getAwsErrorCode() ?? '',
      'message' => $e->getAwsErrorMessage() ?? $e->getMessage(),
      'type' => $e->getAwsErrorType() ?? '',
      'request_id' => $e->getAwsRequestId() ?? '',
    ];
  }
}
