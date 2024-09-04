<?php
namespace Drupal\poll\Controller;

use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\poll\Entity\PollInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\poll\Plugin\QuestionTypeManager;

class PollQuestionTypeController extends ControllerBase implements ContainerInjectionInterface {
  protected $questionTypeManager;

  protected $formBuilder;

  public function __construct(QuestionTypeManager $question_type_manager, FormBuilder $form_builder) {
    $this->questionTypeManager = $question_type_manager;
    $this->formBuilder = $form_builder;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.question_type'),
      $container->get('form_builder')
    );
  }

  public function questionTypeAjax(Request $request, PollInterface $poll) {
    $method = $request->request->get('_method', 'POST');
    $question_type = $request->request->get('question_type');
    $uuid = $request->request->get('uuid');

    $form_id = $request->request->get('form_id');
    if ($method === 'PUT' && $uuid) {
      return $this->updateQuestionTypeAjax($request, $poll, $question_type, $uuid);
    } else {
      return $this->addQuestionTypeAjax($request, $poll, $question_type, $form_id);
    }
  }

  public function addQuestionTypeAjax(Request $request, PollInterface $poll, $question_type, $form_id) {
    $response = new AjaxResponse();
    $requesrData = $request->request->all();
    // Build the form state
    $form_state = (new FormState())
      ->setValues($requesrData)
      ->set('poll', $poll)
      ->set('question_type', $question_type);

    // Get the form
    $form_state->disableRedirect();
    $form = $this->formBuilder->buildForm('\Drupal\poll\Form\PollQuestionTypeAddForm', $form_state);

    // Validate and submit the form
    if ($form_state->isValidationComplete() || $form_state->isExecuted()) {
      $this->formBuilder->processForm('\Drupal\poll\Form\PollQuestionTypeAddForm', $form, $form_state);
    }

    if ($form_state->hasAnyErrors()) {
      $errors = [];
      foreach ($form_state->getErrors() as $name => $error) {
        $errors[$name] = $error->render();
      }
      //$form = $this->formBuilder->rebuildForm('Drupal\poll\Form\PollQuestionTypeAddForm', $form_state, $form);
      $response->addCommand(new ReplaceCommand('.ui-dialog-content', $form));
      $response->addCommand(new InvokeCommand(NULL, 'showFormErrors', [$errors]));
    } else {
      // If successful, add commands to close the modal and refresh the page
      // No errors, so create and save the question type
      $question_type_instance = $this->questionTypeManager->createInstance($question_type);
      $question_type_instance->setConfiguration($form_state->getValues());

      // Set the weight to be the last in the list
      $current_question_count = count($poll->getQuestionTypes());
      $question_type_instance->setWeight($current_question_count);
      // Add the question type to the poll
      $uuid = $poll->addQuestionType($question_type_instance->getConfiguration());
      //@todo inject the service messenger
      \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
      \Drupal::messenger()->addStatus($this->t('Question type added successfully.'));
      // Save the poll entity
      $poll->save();
      $url = Url::fromRoute('entity.poll.edit_questions', ['poll' => $poll->id()]);
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand($url->toString()));
      //$response->addCommand(new InvokeCommand(NULL, 'showMessage', ['Question type added successfully.', 'status']));
    }
    return $response;
  }

  /*
   * Handle the click on the submit button: "Update question type" on the modal
   */
  protected function updateQuestionTypeAjax(Request $request, PollInterface $poll, $question_type, $uuid) {
    $response = new AjaxResponse();

    try {
      $question_type = $poll->getQuestionType($uuid);

      if (!$question_type) {
        throw new \Exception('Question type not found');
      }
      $requestData = $request->request->all();

      $form_state = (new FormState())
        ->setValues($requestData)
        ->set('poll', $poll)
        ->set('question_type', $question_type)
        ->set('uuid', $uuid);
      $form_state->disableRedirect();
      $form = $this->formBuilder->buildForm('Drupal\poll\Form\PollQuestionTypeEditForm', $form_state);

      if ($form_state->isValidationComplete() || $form_state->isExecuted()) {
        $this->formBuilder->processForm('Drupal\poll\Form\PollQuestionTypeEditForm', $form, $form_state);
      }

      if ($form_state->hasAnyErrors()) {
        $errors = [];
        foreach ($form_state->getErrors() as $name => $error) {
          $errors[$name] = $error->render();
        }
        $form = $this->formBuilder->rebuildForm('Drupal\poll\Form\PollQuestionTypeEditForm', $form_state, $form);
        $response->addCommand(new ReplaceCommand('.ui-dialog-content', $form));
        $response->addCommand(new InvokeCommand(NULL, 'showFormErrors', [$errors]));
      } else {
        $question_type->setConfiguration($form_state->getValues());
        $poll->save();
        //@todo inject the service messenger
        \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
        \Drupal::messenger()->addStatus($this->t('Question type updated successfully.'));

        $url = Url::fromRoute('entity.poll.edit_questions', ['poll' => $poll->id()]);
        $response->addCommand(new CloseModalDialogCommand());
        $response->addCommand(new RedirectCommand($url->toString()));
        $response->addCommand(new InvokeCommand(NULL, 'showMessage', ['Question type updated successfully.', 'status']));
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('poll')->error('Error updating question type: @error', ['@error' => $e->getMessage()]);
      $response->addCommand(new InvokeCommand(NULL, 'showMessage', ['An error occurred while updating the question type.', 'error']));
    }

    return $response;
  }
}
