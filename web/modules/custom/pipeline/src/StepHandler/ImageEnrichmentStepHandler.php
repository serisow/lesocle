<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles Image Enrichment steps.
 */
class ImageEnrichmentStepHandler implements StepHandlerInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new ImageEnrichmentStepHandler.
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
    // Format ImageEnrichmentStep data to be compatible with UploadImageStep
    // Include the duration and text blocks configuration
    $step_data['image_enrichment_config']['duration'] = (float) ($configuration['duration'] ?? 5.0);
    // Include text blocks if available
    if (!empty($configuration['text_blocks'])) {
      $step_data['image_enrichment_config']['text_blocks'] = $configuration['text_blocks'];
    }
    // Note: The actual image file data will be populated during execution by the Go service
    // We're just ensuring the structure is compatible for the Go service to process
    $this->logger->debug('ImageEnrichmentStep data prepared for Go service: @data', [
      '@data' => json_encode($step_data),
    ]);
  }
}
