<?php
namespace Drupal\pipeline;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Plugin\LLMServiceManager;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractLLMStepType extends ConfigurableStepTypeBase  implements StepTypeExecutableInterface {
  /**
   * @var \Drupal\pipeline\Plugin\LLMServiceManager
   */
  protected $llmServiceManager;

  public function __construct(
    array $configuration,
    $plugin_id, $plugin_definition,
    LoggerInterface $logger,
    RequestStack $request_stack,
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    LLMServiceManager $llm_service_manager
    ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger,  $request_stack, $entity_type_manager, $form_builder);
    $this->llmServiceManager = $llm_service_manager;
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
      $container->get('plugin.manager.llm_service')
    );
  }
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $llm_config_options = $this->getLLMConfigOptions();
    $form['llm_config'] = [
      '#type' => 'select',
      '#title' => $this->t('LLM Configuration'),
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

    if (!empty($context['results'])) {
      $previous_result = end($context['results']);
      $prompt = str_replace('{PREVIOUS_STEP_RESULT}', $previous_result, $prompt);
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

    $this->configuration['response'] = $response;
    // Store the response in the context for the next step
    $context['last_response'] = $response;
    return $response;
  }


  protected function getServiceIdForModel($model_name) {
    $model_service_map = [
      'gpt-3.5-turbo' => 'openai',
      'gpt-4' => 'openai',
      'dall-e-3' => 'openai_image',
      'gemini-1.5-flash' => 'gemini',
      'claude-3-5-sonnet-20240620' => 'anthropic',
      'claude-3-opus-20240229' => 'anthropic',
    ];
    return $model_service_map[$model_name] ?? 'openai'; // Default to 'openai' if not found
  }
}
