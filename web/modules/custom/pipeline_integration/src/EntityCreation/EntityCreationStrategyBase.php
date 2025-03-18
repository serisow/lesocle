<?php
namespace Drupal\pipeline_integration\EntityCreation;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pipeline\Service\MediaCreationService;

abstract class EntityCreationStrategyBase implements EntityCreationStrategyInterface
{
  protected $entityTypeManager;
  protected $mediaCreationService;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MediaCreationService       $media_creation_service,
  )
  {
    $this->entityTypeManager = $entity_type_manager;
    $this->mediaCreationService = $media_creation_service;
  }

  protected function processFeaturedImageData(array $stepResults): ?int
  {
    foreach ($stepResults as $step) {
      if ($step['output_type'] === 'featured_image') {
        $image_data = json_decode($step['data'], true);
        if ($image_data) {
          return $this->mediaCreationService->createImageMedia($image_data);
        }
      }
    }
    return null;
  }

  protected function processSeoMetadata(array $stepResults): ?array
  {
    foreach ($stepResults as $step) {
      if ($step['output_type'] === 'seo_metadata') {
        $seo_data = preg_replace('/^```json\s*|\s*```$/s', '', $step['data']);
        $seo_content = json_decode(trim($seo_data), true);
        if (json_last_error() === JSON_ERROR_NONE
          && isset($seo_content['title'])
          && isset($seo_content['summary'])) {
          return $seo_content;
        }
      }
    }
    return null;
  }

  protected function processTerms(array $stepResults): array {
    $selected_terms = [];
    foreach ($stepResults as $step) {
      if ($step['output_type'] === 'taxonomy_term') {
        $taxonomy_data = preg_replace('/^```json\s*|\s*```$/s', '', $step['data']);
        $taxonomy_content = json_decode(trim($taxonomy_data), true);

        if (json_last_error() === JSON_ERROR_NONE
          && isset($taxonomy_content['selected_terms'])
          && is_array($taxonomy_content['selected_terms'])) {
          foreach ($taxonomy_content['selected_terms'] as $tid) {
            if (is_numeric($tid)) {
              $selected_terms[] = ['target_id' => (int)$tid];
            }
          }
        }
      }
    }
    return $selected_terms;
  }
}
