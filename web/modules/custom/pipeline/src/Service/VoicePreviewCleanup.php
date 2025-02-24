<?php

namespace Drupal\pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Service for cleaning up voice preview files.
 */
class VoicePreviewCleanup
{
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Constructs a new VoicePreviewCleanup.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PrivateTempStoreFactory    $temp_store_factory
  )
  {
    $this->entityTypeManager = $entity_type_manager;
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * Cleanup old preview files.
   */
  public function cleanup()
  {
    $tempstore = $this->tempStoreFactory->get('pipeline_voice_previews');
    $file_storage = $this->entityTypeManager->getStorage('file');

    $fids = $tempstore->get('voice_previews');
    if (is_array($fids)) {
      foreach ($fids as $fid) {
        if ($file = $file_storage->load($fid)) {
          $file->delete();
        }
      }
      $tempstore->delete('voice_previews');
    }
  }
}
