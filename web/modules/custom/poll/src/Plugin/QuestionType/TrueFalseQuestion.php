<?php
namespace Drupal\poll\Plugin\QuestionType;

use Drupal\poll\ConfigurableQuestionTypeBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'True/False' question type.
 *
 * @QuestionType(
 *   id = "true_false",
 *   label = @Translation("True/False Question"),
 *   description = @Translation("A question with True or False as possible answers.")
 * )
 */
class TrueFalseQuestion extends ConfigurableQuestionTypeBase
{

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return ['question_text' => ''];
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
  }


  public function formatAnswer($answer): string {
    if (in_array($answer, ['true', 'false'])) {
      return $answer;
    }
    return $answer == '0' ? 'false' : 'true';
  }


}
