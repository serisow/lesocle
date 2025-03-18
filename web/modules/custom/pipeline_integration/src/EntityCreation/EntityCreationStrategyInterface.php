<?php
namespace Drupal\pipeline_integration\EntityCreation;

interface EntityCreationStrategyInterface {
  /**
   * Creates an entity from the provided step results.
   *
   * @param array $stepResults
   *   The results from previous pipeline steps.
   * @param array $context
   *   The pipeline execution context.
   *
   * @return array
   *   Array containing the created entity info.
   */
  public function createEntity(array $stepResults, array &$context): array;

  /**
   * Checks if this strategy supports the given bundle.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return bool
   *   TRUE if this strategy supports the bundle.
   */
  public function supportsBundle(string $entityTypeId, string $bundle): bool;
}
