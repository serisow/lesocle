<?php
namespace Drupal\pipeline\Plugin\LLMService;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\LLMServiceInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LLMService(
 *   id = "elevenlabs",
 *   label = @Translation("ElevenLabs Service")
 * )
 */
class ElevenLabsService extends PluginBase implements LLMServiceInterface, ContainerFactoryPluginInterface
{
  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new ElevenLabsService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    array                         $configuration,
                                  $plugin_id,
                                  $plugin_definition,
    ClientInterface               $http_client,
    LoggerChannelFactoryInterface $logger_factory
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('logger.factory')
    );
  }

  /**
   * Calls the OpenAI API.
   *
   * @param array $config
   *   The LLM Config.
   * @param string $prompt
   *   The prompt to send to the API.
   *
   * @return string
   *   The response from the API.
   *
   * @throws \Exception
   */
  public function callLLM(array $config, string $prompt): string {
    $maxRetries = 3;
    $retryDelay = 5;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        // Validate configuration
        if (empty($config['api_key']) || empty($config['parameters']['voice_id'])) {
          throw new \Exception('Missing required configuration: API key or voice ID');
        }

        $response = $this->httpClient->post($config['api_url'] . '/' . $config['parameters']['voice_id'], [
          'headers' => [
            'xi-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'audio/mpeg',
          ],
          'json' => [
            'text' => $prompt,
            'model_id' => $config['model_name'],
            'voice_settings' => [
              'stability' => (float) ($config['parameters']['stability'] ?? 0.5),
              'similarity_boost' => (float) ($config['parameters']['similarity_boost'] ?? 0.75),
              'style' => (float) ($config['parameters']['style'] ?? 0),
              'use_speaker_boost' => (bool) ($config['parameters']['use_speaker_boost'] ?? true),
            ],
          ],
          'timeout' => 120,
        ]);

        if ($response->getStatusCode() === 200) {
          // Save the audio file
          $directory = 'private://pipeline/audio/' . date('Y-m');
          if (!\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
            throw new \Exception('Failed to create directory: ' . $directory);
          }

          $filename = uniqid('tts_', true) . '.mp3';
          $uri = $directory . '/' . $filename;

          $file = \Drupal::service('file.repository')->writeData(
            $response->getBody()->getContents(),
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
        }

        throw new \Exception('Unexpected response status: ' . $response->getStatusCode());

      } catch (RequestException $e) {
        $errorDetails = $this->extractErrorDetails($e);

        if ($errorDetails['status_code'] === 429) {
          $this->loggerFactory->get('pipeline')->warning(
            'ElevenLabs rate limit hit on attempt @attempt of @max. Waiting @delay seconds.',
            [
              '@attempt' => $attempt,
              '@max' => $maxRetries,
              '@delay' => $retryDelay,
            ]
          );

          if ($attempt < $maxRetries) {
            sleep($retryDelay);
            continue;
          }
        }

        throw new \Exception(sprintf(
          'ElevenLabs API Error (Status: %d, Type: %s): %s',
          $errorDetails['status_code'],
          $errorDetails['error_type'],
          $errorDetails['error_message']
        ));
      }
    }

    throw new \Exception('Failed to generate audio after ' . $maxRetries . ' attempts');
  }

  /**
   * Extracts error details from ElevenLabs API response.
   */
  protected function extractErrorDetails(RequestException $e): array
  {
    $response = $e->getResponse();
    $statusCode = $response ? $response->getStatusCode() : 0;
    $body = '';
    $errorType = '';
    $errorMessage = '';

    if ($response) {
      $body = $response->getBody()->getContents();
      $errorData = json_decode($body, TRUE);
      if (isset($errorData['detail'])) {
        $errorMessage = is_array($errorData['detail'])
          ? $errorData['detail']['message']
          : $errorData['detail'];
        $errorType = $errorData['detail']['status'] ?? 'Unknown';
      }
    }

    return [
      'status_code' => $statusCode,
      'error_type' => $errorType,
      'error_message' => $errorMessage ?: $e->getMessage(),
      'raw_body' => $body,
    ];
  }
}
