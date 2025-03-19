<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\pipeline\Plugin\ModelManager;

/**
 * Handles LLM steps.
 */
class LlmStepHandler implements StepHandlerInterface {

  /**
   * The model manager.
   *
   * @var \Drupal\pipeline\Plugin\ModelManager
   */
  protected $modelManager;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new LlmStepHandler.
   *
   * @param \Drupal\pipeline\Plugin\ModelManager $model_manager
   *   The model manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ModelManager $model_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->modelManager = $model_manager;
    $this->logger = $logger_factory->get('pipeline');
  }

  /**
   * {@inheritdoc}
   */
  public function processStepData(array &$step_data, array $configuration, EntityTypeManagerInterface $entity_type_manager) {
    $step_data['prompt'] = $configuration['prompt'] ?? '';
    $step_data['llm_config'] = $configuration['llm_config'] ?? '';

    if (isset($configuration['llm_config'])) {
      /** @var \Drupal\pipeline\Entity\LLMConfig $llm_config */
      $llm_config = $entity_type_manager->getStorage('llm_config')->load($configuration['llm_config']);
      if ($llm_config) {
        $model_plugin = $this->modelManager->createInstanceFromModelName($llm_config->getModelName());
        $step_data['llm_service'] = [
          'id' => $llm_config->id(),
          'label' => $llm_config->label(),
          'api_key' => $llm_config->getApiKey(),
          'model_name' => $llm_config->getModelName(),
          'api_url' => $llm_config->getApiUrl(),
          'parameters' => $llm_config->getParameters(),
          'service_name' => $model_plugin->getServiceId(),
        ];
      }
    }
  }
}
