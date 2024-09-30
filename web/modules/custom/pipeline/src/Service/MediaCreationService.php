<?php
namespace Drupal\pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;

class MediaCreationService {
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
}
