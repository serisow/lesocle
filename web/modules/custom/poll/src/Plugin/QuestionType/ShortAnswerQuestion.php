<?php
namespace Drupal\poll\Plugin\QuestionType;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\poll\ConfigurableQuestionTypeBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Short Answer' question type.
 *
 * @QuestionType(
 *   id = "short_answer",
 *   label = @Translation("Short Answer Question"),
 *   description = @Translation("A question that requires a brief written response.")
 * )
 */
class ShortAnswerQuestion extends ConfigurableQuestionTypeBase
{
  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration()
  {
    return [
      'question_text' => '',
      'advanced_settings' => [
        'max_words' => 100,
      ]
    ];
  }
  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
    ];

    $form['advanced_settings']['max_words'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Words'),
      '#default_value' => $this->configuration['advanced_settings']['max_words'],
      '#description' => $this->t('Maximum number of words allowed in the answer.'),
      '#required' => TRUE,
      '#min' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalSubmitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['question_text'] = $form_state->getValue(['data','question_text']);
    $this->configuration['advanced_settings']['max_words'] = $form_state->getValue(['data','advanced_settings', 'max_words']);
  }

  public function formatAnswer($answer): string {
    return trim($answer);
  }

}
