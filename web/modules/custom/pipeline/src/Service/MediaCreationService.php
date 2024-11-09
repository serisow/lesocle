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
