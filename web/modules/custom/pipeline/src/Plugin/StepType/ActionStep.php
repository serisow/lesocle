<?php
namespace Drupal\pipeline\Plugin\StepType;

use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Plugin\ActionServiceManager;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @StepType(
 *   id = "action_step",
 *   label = @Translation("Action Step"),
 *   description = @Translation("A step to perform Drupal actions or call external APIs.")
 * )
 */
class ActionStep extends ConfigurableStepTypeBase implements StepTypeExecutableInterface {
  protected $actionServiceManager;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->actionServiceManager = $container->get('plugin.manager.action_service');
    return $instance;
  }

  protected function additionalDefaultConfiguration() {
    return [
      'action_config' => '',
    ];
  }

  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::additionalConfigurationForm($form, $form_state);

    $form['action_config'] = [
      '#type' => 'select',
      '#title' => $this->t('Action Configuration'),
      '#options' => $this->getActionConfigOptions(),
      '#default_value' => $this->configuration['action_config'],
      '#required' => TRUE,
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['action_config'] = $form_state->getValue(['data', 'action_config']);
  }

  protected function getActionConfigOptions() {
    $options = [];
    $action_config_storage = $this->entityTypeManager->getStorage('action_config');
    $action_configs = $action_config_storage->loadMultiple();

    foreach ($action_configs as $action_config) {
      $options[$action_config->id()] = $action_config->label();
    }
    return $options;
  }

  public function execute(array &$context): string {
    $config = $this->getConfiguration()['data'];
    $action_config_id = $config['action_config'];
    $action_config = $this->entityTypeManager->getStorage('action_config')->load($action_config_id);

    if (!$action_config) {
        throw new \Exception("Action Configuration not found: " . $action_config_id);
    }

    $action_type = $action_config->get('action_type');
    $action_service = $this->actionServiceManager->createInstance($action_type);

    // Retrieve the last LLM step's response
    //$last_response = $context['last_response'] ?? '';
    $memory = $context['memory'] ?? [];

    // Add the last response to the action config
    $action_config_array = $action_config->toArray();
    //$action_config_array['last_response'] = $last_response;
    $action_config_array['memory'] = $memory;

    return $action_service->executeAction($action_config_array, $context);
  }
}
