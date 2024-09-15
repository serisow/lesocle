<?php
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

    // search the output_key
    $search = '';
    if (array_key_exists($this->getUuid(), $context['memory'])) {
      $search = '{'. $config['step_output_key'] .'}';
    }
    if (!empty($context['results'])) {
      $previous_result = end($context['results']);
      $prompt = str_replace( $search, $previous_result, $prompt);
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
    // Check if the response is an image
    if ($service_id === 'openai_image') {
      $context['image_data'] = $response;
    } else {
      $context['last_response'] = $response;
    }
    return $response;
  }


  protected function getServiceIdForModel($model_name) {
    $plugin = $this->modelManager->createInstanceFromModelName($model_name);
    return $plugin->getServiceId();
  }

  protected function processOutput($response, array &$memory) {
    $memory[$this->getPluginId()] = $response;
  }

  protected function buildPrompt($prompt, array $memory) {
    foreach ($memory as $key => $value) {
      $prompt = str_replace("{{$key}}", $value, $prompt);
    }
    return $prompt;
  }
}
