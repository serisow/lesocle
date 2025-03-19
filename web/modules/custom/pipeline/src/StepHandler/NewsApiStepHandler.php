<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles News API steps.
 */
class NewsApiStepHandler implements StepHandlerInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new NewsApiStepHandler.
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
    $step_data['news_api_config'] = [
      'query' => $configuration['query'] ?? '',
      'advanced_params' => [
        'language' => $configuration['advanced_params']['language'] ?? 'en',
        'sort_by' => $configuration['advanced_params']['sort_by'] ?? 'publishedAt',
        'page_size' => $configuration['advanced_params']['page_size'] ?? 20,
        'date_range' => [
          'from' => $configuration['advanced_params']['date_range']['from'] ?? null,
          'to' => $configuration['advanced_params']['date_range']['to'] ?? null,
        ],
      ],
    ];
  }
}
