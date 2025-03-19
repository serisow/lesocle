<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles Action steps.
 */
class ActionStepHandler implements StepHandlerInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new ActionStepHandler.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('pipeline');
  }

  /**
   * {@inheritdoc}
   */
  public function processStepData(array &$step_data, array $configuration, EntityTypeManagerInterface $entity_type_manager) {
    $step_data['action_config'] = $configuration['action_config'] ?? '';
    
    // Load and add action configuration details
    if (isset($configuration['action_config'])) {
      $action_config = $entity_type_manager->getStorage('action_config')
        ->load($configuration['action_config']);

      if ($action_config) {
        /** @var \Drupal\pipeline\Entity\ActionConfig $action_config */
        $action_configuration = $action_config->getConfiguration();
        if (empty($action_configuration)) {
          $action_configuration = (object) [];
        }
        $step_data['action_details'] = [
          'id' => $action_config->id(),
          'label' => $action_config->label(),
          'action_service' => $action_config->getActionService(),
          'execution_location' => $action_config->get('execution_location') ?? 'drupal',
          'configuration' => $action_configuration,
        ];
      }
    }
  }
}
