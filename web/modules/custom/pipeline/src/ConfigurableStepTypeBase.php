<?php
namespace Drupal\pipeline;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a base class for configurable step types.
 *
 * @see \Drupal\pipeline\Plugin\StepType\Annotation\StepType
 * @see \Drupal\pipeline\ConfigurableStepTypeInterface
 * @see \Drupal\pipeline\Plugin\StepTypeInterface
 * @see \Drupal\pipeline\StepTypeBase
 * @see \Drupal\pipeline\Plugin\StepTypeManager
 * @see plugin_api
 */
abstract class ConfigurableStepTypeBase extends StepTypeBase implements ConfigurableStepTypeInterface {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'step_description' => '',
        'step_output_key' => '',
        'required_steps' => '',
        'response' => ''
      ] + $this->additionalDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $step_description = '';
    if ($form_state->has('step_description')) {
      $step_description = $form_state->get('step_description');
    } elseif (isset($this->configuration['step_description'])) {
      $step_description = $this->configuration['step_description'];
    }

    $form['step_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Step description'),
      '#default_value' => $step_description,
      '#description' => $this->t('Enter the text of the description.'),
      '#required' => TRUE,
      '#rows' => 2,
      '#weight' => -5
    ];
    $form['step_output_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Output Key'),
      '#description' => $this->t('The key under which this step\'s output will be stored in the pipeline context.'),
      '#default_value' => $this->configuration['step_output_key'],
      '#required' => FALSE,
      '#weight' => 1
    ];
    $form['required_steps'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Required Steps'),
      '#description' => $this->t('Enter one step output key per line. These are the steps from which we expect results.'),
      '#default_value' => is_array($this->configuration['required_steps'])
        ? implode("\r\n", $this->configuration['required_steps'])
        : $this->configuration['required_steps'],
      '#required' => FALSE,
      '#weight' => 2
    ];

    $form['response'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Response'),
      '#default_value' => $this->configuration['response'],
      '#disabled' => TRUE,
      '#weight' => 3
    ];
    return $this->additionalConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state){}


  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['step_description'] = $form_state->getValue('step_description');
    $this->configuration['step_output_key'] = $form_state->getValue('step_output_key');
    $this->configuration['response'] = $form_state->getValue('response');
    $required_steps = $form_state->getValue(['data', 'required_steps']);
    $this->configuration['required_steps'] = array_filter(explode("\r\n", $required_steps));
    $this->additionalSubmitConfigurationForm($form, $form_state);
  }

  /**
   * Provides additional default configuration for the step type.
   *
   * @return array
   *   An associative array with additional default configuration.
   */
  protected function additionalDefaultConfiguration() {
    return [];
  }

  /**
   * Builds additional configuration form elements for the step type.
   *
   * @param array $form
   *   The form array to add to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The modified form array.
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Submits additional configuration form elements for the step type.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function additionalSubmitConfigurationForm(array &$form, FormStateInterface $form_state) {}
}
