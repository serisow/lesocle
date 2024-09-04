<?php
namespace Drupal\poll\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\poll\Entity\PollInterface;

/**
 * Form for deleting a question type.
 */
class PollQuestionTypeDeleteForm extends ConfirmFormBase
{

  /**
   * The poll containing the question type to be deleted.
   *
   * @var \Drupal\poll\Entity\PollInterface
   */
  protected $poll;

  /**
   * The question type to be deleted.
   *
   * @var \Drupal\poll\Plugin\QuestionTypeInterface
   */
  protected $questionType;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the @question_type question type from the %poll poll?',
      ['%poll' => $this->poll->label(), '@question_type' => $this->questionType->label()]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->poll->toUrl('edit-form');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'poll_question_type_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PollInterface $poll = NULL, $question_type = NULL) {
    $this->poll = $poll;
    $this->questionType = $this->poll->getQuestionType($question_type);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->poll->deleteQuestionType($this->questionType);
    $this->messenger()->addMessage($this->t('The question type %name has been deleted.', ['%name' => $this->questionType->label()]));
    $form_state->setRedirectUrl(Url::fromRoute('entity.poll.edit_questions', ['poll' => $this->poll->id()]));
  }

}
