<?php

namespace Drupal\pipeline\Plugin\StepType;

use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Service\OpenAIService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'OpenAI Test' step type.
 *
 * @StepType(
 *   id = "openai_test",
 *   label = @Translation("OpenAI Test Step"),
 *   description = @Translation("A step to test OpenAI API calls.")
 * )
 */
class OpenAITestStep extends ConfigurableStepTypeBase {

  /**
   * The OpenAI service.
   *
   * @var \Drupal\pipeline\Service\OpenAIService
   */
  protected $openAIService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->openAIService = $container->get('pipeline.openai_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return [
      'openai_api_url' => '',
      'openai_api_key' => '',
      'prompt' => '',
      'response' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['openai_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI API URL'),
      '#default_value' => $this->configuration['openai_api_url'],
      '#required' => TRUE,
    ];

    $form['openai_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI API Key'),
      '#default_value' => $this->configuration['openai_api_key'],
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $this->configuration['prompt'],
      '#required' => TRUE,
    ];

    $form['response'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Response'),
      '#default_value' => $this->configuration['response'],
      '#disabled' => TRUE,
    ];

    /*$form['test_api'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test API Call'),
      '#submit' => [[$this, 'testApiCall']],
      '#ajax' => [
        'callback' => [$this, 'updateResponseField'],
        'wrapper' => 'edit-data-response',
      ],
    ];*/

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['openai_api_url'] = $form_state->getValue(['data', 'openai_api_url']);
    $this->configuration['openai_api_key'] = $form_state->getValue(['data', 'openai_api_key']);
    $this->configuration['prompt'] = $form_state->getValue(['data', 'prompt']);
    $this->configuration['response'] = $form_state->getValue(['data', 'response']);
  }

  /**
   * Submit handler for the API test button.
   */
  public function testApiCall(array &$form, FormStateInterface $form_state) {
    $api_url = $form_state->getValue(['data', 'openai_api_url']);
    $api_key = $form_state->getValue(['data', 'openai_api_key']);
    $prompt = $form_state->getValue(['data', 'prompt']);

    try {
      $response = $this->openAIService->callOpenAI($api_url, $api_key, $prompt);
      $form_state->setValue(['data', 'response'], $response);
      $this->configuration['response'] = $response;
    }
    catch (\Exception $e) {
      $form_state->setValue(['data', 'response'], 'Error: ' . $e->getMessage());
    }

    $form_state->setRebuild();
  }

  /**
   * Ajax callback to update the response field.
   */
  public function updateResponseField(array &$form, FormStateInterface $form_state) {
    return $form['data']['response'];
  }
}
