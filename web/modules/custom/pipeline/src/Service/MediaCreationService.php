<?php
/**
 * Service for creating media entities from files.
 *
 * Simple service that creates a media entity of type 'image' from a file ID.
 * Used after image files are created to make them available as media entities.
 *
 * @see \Drupal\pipeline\Service\ImageDownloadService
 */
namespace Drupal\pipeline\Service;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MediaCreationService implements ContainerInjectionInterface {
  /**
   * The entity_type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }
  public function createImageMedia(array $image_info): ?int {
    try {
      $file = $this->entityTypeManager->getStorage('file')->load($image_info['file_id']);

      if (!$file instanceof FileInterface) {
        throw new \Exception('Invalid file ID.');
      }

      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => 'image',
        'name' => $image_info['filename'],
        'field_media_image' => [
          'target_id' => $file->id(),
          // TODO: Add alt text to the image should come from the image_info array
          // The LLM should generate a good alt text for the image
          'alt' => 'Generated image for AI article',
        ],
      ]);

      $media->save();
      return $media->id();
    } catch (\Exception $e) {
      \Drupal::logger('pipeline')->error('Error creating media entity: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Creates a video media entity from a file.
   *
   * @param array $video_info
   *   Array containing video information including 'file_id'.
   *
   * @return int|null
   *   The media entity ID, or NULL if creation failed.
   */
  public function createVideoMedia(array $video_info): ?int {
    try {
      if (empty($video_info['file_id'])) {
        throw new \Exception('Invalid file ID.');
      }

      $file = $this->entityTypeManager->getStorage('file')->load($video_info['file_id']);

      if (!$file instanceof FileInterface) {
        throw new \Exception('Invalid file ID.');
      }

      /** @var \Drupal\media\Entity\Media $media */
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => 'video',
        'name' => $video_info['filename'] ?? 'Pipeline generated video',
        'field_media_video_file' => [
          'target_id' => $file->id(),
          'description' => 'Generated video from pipeline',
        ],
      ]);

      // Add duration if available
      if (!empty($video_info['duration']) && $media->hasField('field_duration')) {
        $media->set('field_duration', $video_info['duration']);
      }

      $media->save();
      return $media->id();
    }
    catch (\Exception $e) {
      \Drupal::logger('pipeline')->error('Error creating video media entity: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }
}
