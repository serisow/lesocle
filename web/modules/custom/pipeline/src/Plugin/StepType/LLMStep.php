<?php

namespace Drupal\pipeline\Plugin\StepType;

use Drupal\pipeline\AbstractLLMStepType;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an 'LLM' step type.
 *
 * @StepType(
 *   id = "llm_step",
 *   label = @Translation("LLM Step"),
 *   description = @Translation("A step to issue LLM API calls.")
 * )
 */
class LLMStep extends AbstractLLMStepType {
  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return [
      'prompt' => '',
      'response' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::additionalConfigurationForm($form, $form_state);
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
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::additionalSubmitConfigurationForm($form, $form_state);
    $this->configuration['prompt'] = $form_state->getValue(['data', 'prompt']);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string {
    return parent::execute($context);
  }
  /**
   * Ajax callback to update the response field.
   */
  public function updateResponseField(array &$form, FormStateInterface $form_state) {
    return $form['data']['response'];
  }
}
