<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Default handler for step types without a specific handler.
 */
class DefaultStepHandler implements StepHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function processStepData(array &$step_data, array $configuration, EntityTypeManagerInterface $entity_type_manager) {
    // Simply copy all configuration data to step_data
    foreach ($configuration as $key => $value) {
      $step_data[$key] = $value;
    }
  }

}
