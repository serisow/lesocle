<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Plugin\ModelManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for LLM Config add/edit forms.
 */
class LLMConfigForm extends EntityForm
{

  /**
   * The model manager.
   *
   * @var \Drupal\pipeline\Plugin\ModelManager
   */
  protected $modelManager;

  /**
   * Constructs a new LLMConfigForm.
   *
   * @param \Drupal\pipeline\Plugin\ModelManager $model_manager
   *   The model manager.
   */
  public function __construct(ModelManager $model_manager) {
    $this->modelManager = $model_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.model_manager')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\pipeline\Entity\LLMConfig $llm_config */
    $llm_config = $this->entity;

    // Name field for the LLM Config entity.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('LLM Config Name'),
      '#maxlength' => 255,
      '#default_value' => $llm_config->label(),
      '#description' => $this->t('The name of the LLM configuration.'),
      '#required' => TRUE,
    ];

    // Machine name field (used internally).
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $llm_config->id(),
      '#machine_name' => [
        'exists' => '\Drupal\pipeline\Entity\LLMConfig::load',
      ],
      '#disabled' => !$llm_config->isNew(),
    ];

    // API URL field.
    /*$form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API URL'),
      '#maxlength' => 255,
      '#default_value' => $llm_config->getApiUrl(),
      '#description' => $this->t('The URL of the API endpoint.'),
      '#required' => TRUE,
    ];*/

    // API Key field.
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#maxlength' => 255,
      '#default_value' => $llm_config->getApiKey(),
      '#description' => $this->t('The API key used for authentication.'),
      '#required' => TRUE,
    ];

    $form['model_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Model'),
      '#options' => $this->getModelOptions(),
      '#default_value' => $llm_config->get('model_name'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateModelParameters',
        'wrapper' => 'model-parameters',
      ],
    ];



    $form['parameters'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="model-parameters">',
      '#suffix' => '</div>',
    ];

    $this->buildParametersForm($form, $form_state);

    return $form;
  }

  /**
   * Gets the available model options.
   *
   * @return array
   *   An array of model options.
   */
  protected function getModelOptions() {
    $options = [];
    foreach ($this->modelManager->getDefinitions() as $plugin_id => $definition) {
      $options[$definition['model_name']] = $definition['label'];
    }
    return $options;
  }

  /**
   * Builds the parameters form based on the selected model.
   */
  protected function buildParametersForm(array &$form, FormStateInterface $form_state) {
    $model_name = $form_state->getValue('model_name') ?: $this->entity->get('model_name');
    if ($model_name) {
      $plugin = $this->modelManager->createInstanceFromModelName($model_name);
      $default_params = $plugin->getDefaultParameters();
      $current_params = $this->entity->get('parameters') ?: [];

      foreach ($default_params as $key => $default_value) {
        $form['parameters'][$key] = [
          '#type' => 'textfield',
          '#title' => $this->t('@key', ['@key' => ucfirst(str_replace('_', ' ', $key))]),
          '#default_value' => $current_params[$key] ?? $default_value,
        ];
      }
    }
  }

  /**
   * Ajax callback to update model parameters.
   */
  public function updateModelParameters(array $form, FormStateInterface $form_state) {
    return $form['parameters'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
    // Custom validation logic can go here if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $llm_config = $this->entity;
    $status = $llm_config->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label LLM Config.', [
        '%label' => $llm_config->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label LLM Config was not saved.', [
        '%label' => $llm_config->label(),
      ]), 'error');
    }

    $form_state->setRedirectUrl($llm_config->toUrl('collection'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\pipeline\Entity\LLMConfig $llm_config */
    $llm_config = $this->entity;
    $model_name = $form_state->getValue('model_name');
    $plugin = $this->modelManager->createInstanceFromModelName($model_name);

    $llm_config->setModelName($model_name);
    $llm_config->setApiKey($form_state->getValue('api_key'));
    $llm_config->setParameters($form_state->getValue('parameters'));
    $llm_config->setApiUrl($plugin->getApiUrl());
  }

}

