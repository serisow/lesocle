<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Plugin\ActionServiceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ActionConfigForm extends EntityForm {

  /**
   * The action service manager.
   *
   * @var \Drupal\pipeline\Plugin\ActionServiceManager
   */
  protected $actionServiceManager;

  /**
   * Constructs a new ActionConfigForm.
   *
   * @param \Drupal\pipeline\Plugin\ActionServiceManager $action_service_manager
   *    The action service manager.
   */
  public function __construct(ActionServiceManager $action_service_manager) {
    $this->actionServiceManager = $action_service_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.action_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $action_config = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $action_config->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $action_config->id(),
      '#machine_name' => [
        'exists' => '\Drupal\pipeline\Entity\ActionConfig::load',
      ],
      '#disabled' => !$action_config->isNew(),
    ];

    $form['action_service'] = [
      '#type' => 'select',
      '#title' => $this->t('Action Service'),
      '#options' => $this->getActionServiceOptions(),
      '#default_value' => $action_config->getActionService(),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateActionServiceConfiguration',
        'wrapper' => 'action-service-configuration',
      ],
    ];

    $form['configuration'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'action-service-configuration'],
    ];

    $action_service = $form_state->getValue('action_service') ?: $action_config->getActionService();
    if ($action_service) {
      $this->buildActionServiceConfigurationForm($form['configuration'], $form_state, $action_service);
    }

    return $form;
  }

  /**
   * Ajax callback to update action service configuration fields.
   */
  public function updateActionServiceConfiguration(array &$form, FormStateInterface $form_state) {
    return $form['configuration'];
  }

  /**
   * Builds the configuration form for the selected action service.
   */
  protected function buildActionServiceConfigurationForm(array &$element, FormStateInterface $form_state, $action_service) {
    $action_service_plugin = $this->actionServiceManager->createInstance($action_service);
    $configuration = $this->entity->getConfiguration();
    $element += $action_service_plugin->buildConfigurationForm([], $form_state, $configuration);
  }

  /**
   * Get available action service options.
   */
  protected function getActionServiceOptions() {
    $options = [];
    foreach ($this->actionServiceManager->getDefinitions() as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $action_service = $form_state->getValue('action_service');
    $this->entity->setActionService($action_service);

    $action_service_plugin = $this->actionServiceManager->createInstance($action_service);
    $configuration = $action_service_plugin->submitConfigurationForm($form['configuration'], $form_state);
    $this->entity->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $action_config = $this->entity;
    $status = $action_config->save();

    $message = $status == SAVED_NEW
      ? $this->t('Created the %label Action Configuration.', ['%label' => $action_config->label()])
      : $this->t('Saved the %label Action Configuration.', ['%label' => $action_config->label()]);

    $this->messenger()->addMessage($message);
    $form_state->setRedirectUrl($action_config->toUrl('collection'));
  }
}
