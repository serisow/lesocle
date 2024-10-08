<?php
namespace Drupal\pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Service for downloading and managing images.
 */
class ImageDownloadService
{

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new ImageDownloadService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    ClientInterface               $http_client,
    FileSystemInterface           $file_system,
    FileRepositoryInterface       $file_repository,
    EntityTypeManagerInterface    $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  )
  {
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Downloads an image from a URL and creates a file entity.
   *
   * @param string $url
   *   The URL of the image to download.
   *
   * @return string
   *   JSON encoded string containing file information.
   */
  public function downloadImage($url): string
  {
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
        $media_info = [
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

  /**
   * Gets fallback image information.
   *
   * @return string
   *   JSON encoded string containing fallback file information.
   *
   * @throws \Exception
   */
  protected function getFallbackImageInfo(): string
  {
    $fallback_file = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => 'public://default_ai_workflow_image.png']);
    $fallback_file = reset($fallback_file);

    if (!$fallback_file) {
      throw new \Exception('Fallback image not found.');
    }

    return json_encode([
      'file_id' => $fallback_file->id(),
      'uri' => $fallback_file->getFileUri(),
      'filename' => $fallback_file->getFilename(),
      'mime' => $fallback_file->getMimeType(),
    ]);
  }

}
