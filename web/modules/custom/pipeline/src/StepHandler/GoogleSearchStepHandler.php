<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles Google Search steps.
 */
class GoogleSearchStepHandler implements StepHandlerInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new GoogleSearchStepHandler.
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
    $step_data['google_search_config'] = [
      'query' => $configuration['query'] ?? '',
      'category' => $configuration['category'] ?? '',
      'advanced_params' => $configuration['advanced_params'] ?? [],
    ];
  }
}
