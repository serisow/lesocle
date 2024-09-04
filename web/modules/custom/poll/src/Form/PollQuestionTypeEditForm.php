<?php
namespace Drupal\poll\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\poll\Entity\PollInterface;
use Drupal\poll\Plugin\QuestionTypeInterface;

class PollQuestionTypeEditForm extends PollQuestionTypeFormBase {
  public function getFormId() {
    return 'poll_question_type_edit_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PollInterface $poll = NULL, $question_type = NULL, $uuid = NULL) {
    // If $poll is not passed as an argument, try to get it from form state
    if ($poll === NULL) {
      $poll = $form_state->get('poll');
    }

    // If we still don't have $poll, log an error and return an empty form
    if ($poll === NULL) {
      \Drupal::logger('poll')->error('Poll object not found in PollQuestionTypeEditForm::buildForm');
      return $form;
    }

    $this->poll = $poll;

    // Prepare question_type
    $question_type = $question_type ?? $form_state->get('question_type');
    $uuid = $uuid ?? $form_state->get('uuid');
    $this->questionType = $this->poll->getQuestionType($uuid);


    $form = parent::buildForm($form, $form_state, $poll, $this->questionType, $uuid);
    $form['#title'] = $this->t('Edit @type', ['@type' => $this->questionType->label()]);
    // Check if the form is being loaded in a modal
    if ($this->getRequest()->query->get('modal')) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
      ];
    }
    $form['actions']['submit']['#value'] = $this->t('Update question type');
    $form['#action'] = $poll->toUrl('edit-form')->toString();

    $form['#attributes']['data-dialog-form'] = 'true';
    return $form;
  }
  protected function prepareQuestionType(?string $question_type_id) {
    return $this->poll->getQuestionType($question_type_id);
  }

  /*public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('entity.poll.edit_questions', ['poll' => $this->poll->id()]);
  }*/

  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    // The form is already processed in the controller, so we just need to return NULL
    // to prevent Drupal from trying to generate an AJAX response here
    return NULL;
  }
}
