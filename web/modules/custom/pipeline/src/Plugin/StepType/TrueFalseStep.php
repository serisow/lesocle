<?php
namespace Drupal\pipeline\Plugin\StepType;

use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'True/False' step type.
 *
 * @StepType(
 *   id = "true_false",
 *   label = @Translation("True/False Step"),
 *   description = @Translation("A step with True or False as possible answers.")
 * )
 */
class TrueFalseStep extends ConfigurableStepTypeBase {
  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return ['step_description' => ''];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalSubmitConfigurationForm(array &$form, FormStateInterface $form_state){}

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $step_description = $form_state->getValue('step_description');
    if (empty($step_description) && $step_description !== '0') {
      $form_state->setErrorByName('step_description', $this->t('Step description is required.'));
    }
  }

}
