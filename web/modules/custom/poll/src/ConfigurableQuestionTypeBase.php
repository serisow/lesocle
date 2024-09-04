<?php
namespace Drupal\poll;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a base class for configurable question types.
 *
 * @see \Drupal\poll\Plugin\QuestionType\Annotation\QuestionType
 * @see \Drupal\poll\ConfigurableQuestionTypeInterface
 * @see \Drupal\poll\Plugin\QuestionTypeInterface
 * @see \Drupal\poll\QuestionTypeBase
 * @see \Drupal\poll\Plugin\QuestionTypeManager
 * @see plugin_api
 */
abstract class ConfigurableQuestionTypeBase extends QuestionTypeBase implements ConfigurableQuestionTypeInterface {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'question_text' => '',
      ] + $this->additionalDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $question_text = '';
    if ($form_state->has('question_text')) {
      $question_text = $form_state->get('question_text');
    } elseif (isset($this->configuration['question_text'])) {
      $question_text = $this->configuration['question_text'];
    }

    $form['question_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Question'),
      '#default_value' => $question_text,
      '#description' => $this->t('Enter the text of the question.'),
      '#required' => TRUE,
      '#rows' => 7,
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
    $this->configuration['question_text'] = $form_state->getValue('question_text');
    $this->additionalSubmitConfigurationForm($form, $form_state);
  }

  /**
   * Provides additional default configuration for the question type.
   *
   * @return array
   *   An associative array with additional default configuration.
   */
  protected function additionalDefaultConfiguration() {
    return [];
  }

  /**
   * Builds additional configuration form elements for the question type.
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
   * Submits additional configuration form elements for the question type.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function additionalSubmitConfigurationForm(array &$form, FormStateInterface $form_state) {}
}
