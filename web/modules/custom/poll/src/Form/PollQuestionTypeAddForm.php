<?php
namespace Drupal\poll\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBuilder;
use Drupal\poll\Entity\PollInterface;
use Drupal\poll\Plugin\QuestionTypeInterface;
use Drupal\poll\Plugin\QuestionTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PollQuestionTypeAddForm extends PollQuestionTypeFormBase {
  /**
   * The question type manager.
   *
   * @var \Drupal\poll\Plugin\QuestionTypeManager
   */
  protected $questionTypeManager;


  protected $formBuilder;

  /**
   * Constructs a new PollQuestionTypeAddForm.
   *
   * @param \Drupal\poll\Plugin\QuestionTypeManager $question_type_manager
   *   The question type manager.
   */
  public function __construct(QuestionTypeManager $question_type_manager, FormBuilder $form_builder) {
    $this->questionTypeManager = $question_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.question_type'),
      $container->get('form_builder')
    );
  }

  public function getFormId() {
    return 'poll_question_type_add_form';
  }

  public static function getTitle(PollInterface $poll = NULL, $question_type = NULL) {
    $question_type_manager = \Drupal::service('plugin.manager.question_type');
    $plugin_definition = $question_type_manager->getDefinition($question_type);
    $label = $plugin_definition['label'];
    return t('Add New @type Question', ['@type' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PollInterface $poll = NULL, $question_type = NULL, $uuid = NULL) {
    $poll = $form_state->get('poll');
    $question_type = $form_state->get('question_type');
    // Get the question type plugin instance
    $this->questionType = $this->questionTypeManager->createInstance($question_type);

    if (!$form_state->has('question_text') && $this->questionType instanceof QuestionTypeInterface) {
      $question_text = $this->questionType->getQuestionText();
      if ($question_text !== null) {
        $form_state->set('question_text', $question_text);
      }
    }

    $form = parent::buildForm($form, $form_state, $poll, $this->questionType);

    // Check if the form is being loaded in a modal
    if ($this->getRequest()->query->get('modal')) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
      ];
    }


    $form['#title'] = $this->t('Add %label question type', ['%label' => $this->questionType->label()]);
    $form['actions']['submit']['#value'] = $this->t('Add question type');
    //$form['#action'] = $poll->toUrl('edit-form')->toString();

    // Add a wrapper for AJAX replacement
    $form['#prefix'] = '<div id="question-type-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['data-dialog-form'] = 'true';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    // The form is already processed in the controller, so we just need to return NULL
    // to prevent Drupal from trying to generate an AJAX response here
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareQuestionType(?string $question_type_id) {
    if (!$question_type_id) {
      return NULL;
    }
    $question_type = $this->questionTypeManager->createInstance($question_type_id);
    // Set the initial weight so this question type comes last.
    $question_type->setWeight(count($this->poll->getQuestionTypes()));
    return $question_type;
  }

}
