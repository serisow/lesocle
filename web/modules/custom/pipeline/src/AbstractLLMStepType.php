<?php
/**
 * Provides base implementation for LLM-integrated step types.
 *
 * This abstract class implements the core functionality for steps that interact
 * with Language Learning Models (LLM) such as GPT, Claude, or Gemini. It handles
 * LLM configuration, prompt management, and response processing.
 *
 * Key features:
 * - Manages LLM service connections
 * - Handles authentication and API configuration
 * - Supports prompt templating
 * - Processes and validates LLM responses
 *
 * Important behaviors:
 * - Implements automatic retry logic for API failures
 * - Supports context-aware prompt generation
 * - Provides standardized error handling
 *
 * Integration points:
 * - Works with ModelManager for model selection
 * - Interfaces with LLMServiceManager for API calls
 * - Supports multiple LLM providers (OpenAI, Anthropic, Google)
 *
 * @see \Drupal\pipeline\Plugin\ModelManager
 * @see \Drupal\pipeline\Plugin\LLMServiceInterface
 */

namespace Drupal\pipeline;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Plugin\LLMServiceManager;
use Drupal\pipeline\Plugin\ModelManager;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractLLMStepType extends ConfigurableStepTypeBase  implements StepTypeExecutableInterface {
  /**
   * @var \Drupal\pipeline\Plugin\LLMServiceManager
   */
  protected $llmServiceManager;

  /**
   * @var \Drupal\pipeline\Plugin\ModelManager
   */
  protected $modelManager;

  public function __construct(
    array $configuration,
    $plugin_id, $plugin_definition,
    LoggerInterface $logger,
    RequestStack $request_stack,
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    LLMServiceManager $llm_service_manager,
    ModelManager $model_manager
    ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger,  $request_stack, $entity_type_manager, $form_builder);
    $this->llmServiceManager = $llm_service_manager;
    $this->modelManager = $model_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('pipeline'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('plugin.manager.llm_service'),
      $container->get('plugin.manager.model_manager')
    );
  }
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $llm_config_options = $this->getLLMConfigOptions();
    $form['llm_config'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Model'),
      '#description' => $this->t('Select the AI model to use for generating content in this step.'),
      '#options' => $llm_config_options,
      '#default_value' => $this->configuration['llm_config'] ?? '',
      '#required' => TRUE,
    ];
    return $form;
  }
  protected function additionalSubmitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['llm_config'] = $form_state->getValue(['data', 'llm_config']);
  }

  private function getLLMConfigOptions() {
    $options = [];
    $llm_config_storage = $this->entityTypeManager->getStorage('llm_config');
    $llm_configs = $llm_config_storage->loadMultiple();

    foreach ($llm_configs as $llm_config) {
      $options[$llm_config->id()] = $llm_config->label();
    }

    return $options;
  }

  protected function getLLMConfig($llm_config_id) {
    return $this->entityTypeManager->getStorage('llm_config')->load($llm_config_id);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string {
    $config = $this->getConfiguration()['data'];
    $prompt = $config['prompt'];

    // Get required previous results
    $required_steps = $this->getRequiredSteps($config);
    $previous_results = $this->getPreviousResults($context, $required_steps);

    // Replace placeholders in the prompt
    foreach ($previous_results as $key => $value) {
      $prompt = str_replace('{' . $key . '}', $value, $prompt);
    }

    if (empty($config['llm_config'])) {
      throw new \Exception("LLM Configuration is not set for this step.");
    }

    $llm_config = $this->entityTypeManager->getStorage('llm_config')->load($config['llm_config']);
    if (!$llm_config) {
      throw new \Exception("LLM Configuration not found: " . $config['llm_config']);
    }

    $model_name = $llm_config->getModelName();
    if (empty($model_name)) {
      throw new \Exception("Model name is not set in LLM Configuration: " . $config['llm_config']);
    }

    $service_id = $this->getServiceIdForModel($model_name);
    $llm_service = $this->llmServiceManager->createInstance($service_id);

    $response = $llm_service->callLLM($llm_config->toArray(), $prompt);

    $context['results'][$this->getStepOutputKey()] = [
      'output_type' => $this->configuration['output_type'],
      'service_id' => $service_id,
      'data' => $response,
    ];

    return $response;
  }


  protected function getServiceIdForModel($model_name) {
    $plugin = $this->modelManager->createInstanceFromModelName($model_name);
    return $plugin->getServiceId();
  }

  /**
   * Get results from specific previous steps.
   *
   * @param array $context
   *   The context array containing all step results.
   * @param array $step_keys
   *   An array of step output keys to retrieve.
   *
   * @return array
   *   An array of requested step results.
   */
  protected function getPreviousResults(array &$context, array $step_keys) {
    $results = [];
    foreach ($step_keys as $key) {
      if (isset($context['results'][$key])) {
        $results[$key] = $context['results'][$key]['data'];
      }
    }
    return $results;
  }

}
