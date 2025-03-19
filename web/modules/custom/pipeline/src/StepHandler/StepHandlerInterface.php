<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Interface for step handlers.
 */
interface StepHandlerInterface {

  /**
   * Processes step data.
   *
   * @param array &$step_data
   *   The step data to modify.
   * @param array $configuration
   *   The step configuration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function processStepData(array &$step_data, array $configuration, EntityTypeManagerInterface $entity_type_manager);

}
