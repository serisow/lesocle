<?php
namespace Drupal\pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Service for downloading and processing videos from external sources.
 */
class VideoDownloadService
{

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file repository.
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
   * The media creation service.
   *
   * @var \Drupal\pipeline\Service\MediaCreationService
   */
  protected $mediaCreationService;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new VideoDownloadService.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\pipeline\Service\MediaCreationService $media_creation_service
   *   The media creation service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    ClientInterface               $http_client,
    FileSystemInterface           $file_system,
    FileRepositoryInterface       $file_repository,
    EntityTypeManagerInterface    $entity_type_manager,
    MediaCreationService          $media_creation_service,
    LoggerChannelFactoryInterface $logger_factory
  )
  {
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->mediaCreationService = $media_creation_service;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Downloads a video from a URL and creates a media entity.
   *
   * @param string|array $video_data
   *   The video data as string (JSON) or array. Must contain 'download_url'.
   *
   * @return string
   *   JSON encoded data with file and media entity information.
   *
   * @throws \Exception
   *   If downloading or processing the video fails.
   */
  public function downloadVideo($video_data)
  {
    // Parse the video data if needed
    $data = is_array($video_data) ? $video_data : json_decode($video_data, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE && !is_array($video_data)) {
      $this->loggerFactory->get('pipeline')->error('Invalid JSON data for video download: @error', ['@error' => json_last_error_msg()]);
      throw new \Exception('Invalid video data format.');
    }

    if (empty($data['download_url'])) {
      $this->loggerFactory->get('pipeline')->error('Missing download URL in video data');
      throw new \Exception('Missing download URL in video data.');
    }

    // Create temporary file
    $temp_file = $this->fileSystem->tempnam('temporary://', 'video_');
    $temp_file_path = $this->fileSystem->realpath($temp_file);

    try {
      // Download the file
      $this->loggerFactory->get('pipeline')->info('Downloading video from: @url', ['@url' => $data['download_url']]);
      $response = $this->httpClient->get($data['download_url'], [
        'sink' => $temp_file_path,
        'timeout' => 300, // Increased timeout for video downloads
      ]);

      if ($response->getStatusCode() !== 200) {
        throw new \Exception(sprintf('Failed to download video. HTTP status code: %d', $response->getStatusCode()));
      }

      // Create directory for permanent storage
      $directory = 'private://pipeline/videos/' . date('Y-m');
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      // Determine filename
      $filename = !empty($data['filename']) ? $data['filename'] : 'video_' . uniqid() . '.mp4';
      $uri = $directory . '/' . $filename;

      // Create permanent file
      $file = $this->fileRepository->writeData(
        file_get_contents($temp_file_path),
        $uri,
        FileExists::Replace
      );

      if (!$file) {
        throw new \Exception('Failed to create permanent file.');
      }

      // Set file to permanent
      $file->setPermanent();
      $file->save();

      // Create media entity
      $media_id = $this->createVideoMedia($file, $filename, $data);

      // Return the file information
      $result = [
        'file_id' => $file->id(),
        'media_id' => $media_id,
        'uri' => $file->getFileUri(),
        'url' => $file->createFileUrl(FALSE),
        'mime_type' => $file->getMimeType(),
        'filename' => $file->getFilename(),
        'size' => $file->getSize(),
        'duration' => $data['duration'] ?? NULL,
        'slides' => $data['slides'] ?? [],
        'timestamp' => \Drupal::time()->getCurrentTime(),
      ];

      $this->loggerFactory->get('pipeline')->info('Successfully created video media entity: @media_id', ['@media_id' => $media_id]);

      return json_encode($result);
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error processing video: @error', ['@error' => $e->getMessage()]);
      throw $e;
    } finally {
      // Clean up temporary file
      if (file_exists($temp_file_path)) {
        $this->fileSystem->delete($temp_file);
      }
    }
  }

  /**
   * Creates a video media entity from a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   * @param string $name
   *   The name for the media entity.
   * @param array $data
   *   Additional metadata for the media entity.
   *
   * @return int
   *   The ID of the created media entity.
   *
   * @throws \Exception
   *   If the media entity cannot be created.
   */
  protected function createVideoMedia($file, $name, array $data)
  {
    try {
      // Create a media entity
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => 'video',
        'name' => $name,
        'field_media_video_file' => [
          'target_id' => $file->id(),
          'description' => 'Pipeline-generated video',
        ],
        'status' => 1,
      ]);

      // Add additional metadata if available
      if (!empty($data['duration'])) {
        // Check if the field exists before setting it
        if ($media->hasField('field_duration')) {
          $media->set('field_duration', $data['duration']);
        }
      }

      $media->save();
      return $media->id();
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Failed to create video media entity: @error', ['@error' => $e->getMessage()]);
      throw new \Exception('Failed to create video media entity: ' . $e->getMessage());
    }
  }
}
