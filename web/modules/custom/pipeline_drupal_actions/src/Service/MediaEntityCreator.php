<?php
namespace Drupal\pipeline_drupal_actions\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileInterface;

/**
 * Service for creating media entities from files.
 */
class MediaEntityCreator
{

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
   * Constructs a new MediaEntityCreator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface    $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  )
  {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Creates a media entity from a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   * @param string $bundle
   *   The media bundle (type).
   * @param string|null $name
   *   The media entity name (optional).
   *
   * @return int|null
   *   The created media entity ID, or NULL if creation failed.
   */
  public function createMediaEntityFromFile(FileInterface $file, string $bundle = 'video', ?string $name = NULL): ?int
  {
    try {
      // Determine the appropriate source field based on bundle type
      $sourceField = $this->getSourceFieldForBundle($bundle);
      if (!$sourceField) {
        throw new \Exception("Could not determine source field for media bundle: $bundle");
      }

      // Create a default name if none provided
      if ($name === NULL) {
        $name = $this->generateMediaName($file, $bundle);
      }

      // Create the media entity
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => $bundle,
        'name' => $name,
        $sourceField => [
          'target_id' => $file->id(),
          'description' => 'Automatically-generated ' . $bundle,
        ],
        'status' => 1,
      ]);

      $media->save();
      return $media->id();
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error creating media entity: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Gets the source field name for a media bundle.
   *
   * @param string $bundle
   *   The media bundle.
   *
   * @return string|null
   *   The source field name, or NULL if not found.
   */
  protected function getSourceFieldForBundle(string $bundle): ?string
  {
    $bundleFields = [
      'video' => 'field_media_video_file',
      'image' => 'field_media_image',
      'audio' => 'field_media_audio_file',
      'document' => 'field_media_document',
      'remote_video' => 'field_media_oembed_video',
    ];

    return $bundleFields[$bundle] ?? NULL;
  }

  /**
   * Generates a name for the media entity based on file properties.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   * @param string $bundle
   *   The media bundle.
   *
   * @return string
   *   The generated name.
   */
  protected function generateMediaName(FileInterface $file, string $bundle): string
  {
    $fileType = ucfirst($bundle);
    return "Automatically-generated {$fileType}: {$file->getFilename()}";
  }

  /**
   * Updates an existing media entity.
   *
   * @param int $mediaId
   *   The media entity ID.
   * @param array $values
   *   The values to update.
   *
   * @return bool
   *   TRUE if the update was successful, FALSE otherwise.
   */
  public function updateMediaEntity(int $mediaId, array $values): bool
  {
    try {
      $media = $this->entityTypeManager->getStorage('media')->load($mediaId);
      if (!$media) {
        return FALSE;
      }

      foreach ($values as $field => $value) {
        $media->set($field, $value);
      }

      $media->save();
      return TRUE;
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error updating media entity: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }
}
