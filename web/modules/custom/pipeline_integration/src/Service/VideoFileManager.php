<?php
namespace Drupal\pipeline_integration\Service;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * Service for managing video files.
 */
class VideoFileManager
{

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new VideoFileManager.
   *
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(
    FileRepositoryInterface $file_repository,
    FileSystemInterface     $file_system
  )
  {
    $this->fileRepository = $file_repository;
    $this->fileSystem = $file_system;
  }

  /**
   * Creates a temporary directory for video processing.
   *
   * @param string $basePath
   *   The base path for the temporary directory.
   *
   * @return string
   *   The path to the created temporary directory.
   *
   * @throws \Exception
   *   If the directory cannot be created.
   */
  public function createTempDirectory($basePath = '/tmp/pipeline_videos'): string
  {
    if (!file_exists($basePath)) {
      if (!mkdir($basePath, 0755, TRUE)) {
        throw new \Exception("Failed to create temporary directory: $basePath");
      }
    }

    return $basePath;
  }

  /**
   * Creates a permanent file from a temporary file.
   *
   * @param string $tempPath
   *   The path to the temporary file.
   * @param string $targetDirectory
   *   The target directory for the permanent file.
   * @param string $extension
   *   The file extension.
   *
   * @return \Drupal\file\FileInterface
   *   The created file entity.
   *
   * @throws \Exception
   *   If the file cannot be created.
   */
  public function createPermanentFile($tempPath, $targetDirectory, $extension = 'mp4'): FileInterface
  {
    // Ensure target directory exists
    $this->fileSystem->prepareDirectory($targetDirectory, FileSystemInterface::CREATE_DIRECTORY);

    // Generate a unique filename
    $filename = uniqid('video_', TRUE) . '.' . $extension;
    $targetUri = $targetDirectory . '/' . $filename;

    // Create file entity
    $file = $this->fileRepository->writeData(
      file_get_contents($tempPath),
      $targetUri,
      FileExists::Replace
    );

    if (!$file) {
      throw new \Exception("Failed to save video file to $targetUri");
    }

    // Set file as permanent
    $file->setPermanent();
    $file->save();

    return $file;
  }

  /**
   * Cleans up temporary files.
   *
   * @param array $files
   *   An array of file paths to clean up.
   */
  public function cleanupTempFiles(array $files = []): void
  {
    foreach ($files as $file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }
  }

  /**
   * Gets a human-readable file size.
   *
   * @param int $bytes
   *   The file size in bytes.
   *
   * @return string
   *   A human-readable file size.
   */
  public function formatFileSize(int $bytes): string
  {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
      $bytes /= 1024;
      $i++;
    }

    return round($bytes, 2) . ' ' . $units[$i];
  }

  /**
   * Gets a Drupal file entity by URI.
   *
   * @param string $uri
   *   The file URI.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if not found.
   */
  public function getFileByUri(string $uri): ?FileInterface
  {
    $files = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties(['uri' => $uri]);

    return !empty($files) ? reset($files) : NULL;
  }
}
