<?php
namespace Drupal\pipeline\Plugin\LLMService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\pipeline\Plugin\LLMServiceInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LLMService(
 *   id = "openai_image",
 *   label = @Translation("OpenAI Image Service")
 * )
 */
class OpenAIImageService extends PluginBase implements LLMServiceInterface, ContainerFactoryPluginInterface
{
  protected $fileRepository;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  protected $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    FileRepositoryInterface $file_repository,
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager
  ){
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->fileRepository = $file_repository;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('file.repository'),
      $container->get('file_system'),
      $container->get('entity_type.manager')
    );
  }

  public function callOpenAIImage(array $config, string $prompt): string
  {
    $maxRetries = 3;
    $retryDelay = 5;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        $response = $this->httpClient->post($config['api_url'], [
          'headers' => [
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'prompt' => $prompt,
            'n' => 1,
            'size' => $config['image_size'] ?? '1024x1024',
            'response_format' => 'url',
          ],
          'timeout' => 120, // Increased timeout
        ]);

        $content = $response->getBody()->getContents();
        $data = json_decode($content, TRUE);

        if (isset($data['data'][0]['url'])) {
          $imageUrl = $data['data'][0]['url'];
          return $this->downloadImage($imageUrl);
        } else {
          throw new \Exception('Unexpected response format from OpenAI Image API.');
        }
      } catch (RequestException $e) {
        $response = $e->getResponse();
        $statusCode = $response ? $response->getStatusCode() : null;
        $body = $response ? $response->getBody()->getContents() : '';

        // Log detailed error
        $this->loggerFactory->get('pipeline')->error('OpenAI Image API error: @status @body', [
          '@status' => $statusCode,
          '@body' => $body,
        ]);

        // Determine if we should retry
        $shouldRetry = $statusCode >= 500 || $e->getCode() === 0; // 5xx or network error
        if ($attempt === $maxRetries || !$shouldRetry) {
          throw new \Exception('Failed to call OpenAI Image API: ' . $e->getMessage());
        }

        // Log and wait before retrying
        $this->loggerFactory->get('pipeline')->warning('Attempt ' . $attempt . ' failed with status ' . $statusCode . '. Retrying in ' . $retryDelay . ' seconds...');
        sleep($retryDelay);
      }

    }
    throw new \Exception('Failed to call OpenAI Image API after exhausting all retry attempts.');
  }

  protected function downloadImage($url): string {
    try {
      $response = $this->httpClient->get($url);
      $content = $response->getBody()->getContents();

      $directory = 'public://generated_images';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      $filename = 'dalle_' . time() . '.png';
      $uri = $directory . '/' . $filename;

      $file = $this->fileRepository->writeData(
        $content,
        "$directory/$filename",
        FileExists::Replace
      );

      if ($file) {
        $media_info =  [
          'file_id' => $file->id(),
          'uri' => $file->getFileUri(),
          'filename' => $file->getFilename(),
          'mime' => $file->getMimeType(),
        ];
        return json_encode($media_info);
      } else {
        throw new \Exception('Failed to save the image file.');
      }
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error downloading image: @error', ['@error' => $e->getMessage()]);
      return $this->getFallbackImageInfo();
    }
  }

  protected function getFallbackImageInfo(): string {
    $fallback_file = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => 'public://default_ai_workflow_image.png']);
    $fallback_file = reset($fallback_file);

    if (!$fallback_file) {
      throw new \Exception('Fallback image not found.');
    }

    return json_encode( [
      'file_id' => $fallback_file->id(),
      'uri' => $fallback_file->getFileUri(),
      'filename' => $fallback_file->getFilename(),
      'mime' => $fallback_file->getMimeType(),
    ]);
  }

  public function callLLM(array $config, string $prompt): string {
    return $this->callOpenAIImage($config, $prompt);
  }
}
