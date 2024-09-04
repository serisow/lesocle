<?php
namespace Drupal\poll\Controller;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\poll\Entity\PollInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\poll\Plugin\QuestionTypeManager;

class MultipleChoiceController extends ControllerBase implements ContainerInjectionInterface {
  protected $questionTypeManager;

  protected $formBuilder;

  public function __construct(QuestionTypeManager $question_type_manager, FormBuilder $form_builder)
  {
    $this->questionTypeManager = $question_type_manager;
    $this->formBuilder = $form_builder;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('plugin.manager.question_type'),
      $container->get('form_builder')
    );
  }

  /**
   * In the modal when a js event is triggered on the input a new option, we hit this handler,
   * to save it and update the modal; this solve a lot of dynamic issues.
   * @param Request $request
   * @param PollInterface $poll
   * @param $question_type
   * @param $uuid
   * @return AjaxResponse
   */
  public function handleOnInputChange(Request $request, PollInterface $poll) {
    $method = $request->request->get('_method', 'POST');
    $question_type = $request->request->get('question_type');
    $uuid = $request->request->get('uuid');
    $form_id = $request->request->get('form_id');


    // In the update case
    if ($method === 'PUT' && $uuid) {
      // Case: Updating a question type
      return $this->handleOnInputChangeUpdate($request, $poll, $question_type, $uuid);
    } else {
      // Case:  New question type creation
      return $this->handleOnInputChangeNew($request, $poll, $question_type, $form_id);
    }
  }

  protected function handleOnInputChangeUpdate(Request $request, PollInterface $poll, $question_type, $uuid) {
    $response = new AjaxResponse();
    try {
      $question_type = $poll->getQuestionType($uuid);

      if (!$question_type) {
        throw new \Exception('Question type not found');
      }
      $form_state = (new FormState())
        ->setValues($request->request->all())
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

        $response->addCommand(new ReplaceCommand('#multiple-choice-options-wrapper', $form['data']['options']));
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('poll')->error('Error updating question type: @error', ['@error' => $e->getMessage()]);
      $response->addCommand(new InvokeCommand(NULL, 'showMessage', ['An error occurred while updating the question type.', 'error']));
    }
    return $response;
  }

  protected function handleOnInputChangeNew(Request $request, PollInterface $poll, $question_type, $form_id) {
    $response = new AjaxResponse();
    // Build the form state
    $form_state = (new FormState())
      ->setValues($request->request->all())
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

      $response->addCommand(new ReplaceCommand('#multiple-choice-options-wrapper', $form['data']['options']));
    }
    return $response;
  }

  /*
   * @TODO: IVESTIGATE THIS METHOD
 * Handle the click on "Add another option" button
*/
  public function addMultipleChoiceOption(Request $request, PollInterface $poll) {
    $response = new AjaxResponse();

    $question_type = $request->request->get('question_type');
    $options = json_decode($request->request->get('options', '[]'), true);

    // Add a new empty option
    $options[] = ['text' => ''];

    // Prepare form state for rebuilding the form
    $form_state = new FormState();
    $form_state->setValue(['data', 'options'], $options);
    $form_state->set('poll', $poll);
    $form_state->set('question_type', $question_type);
    $form_state->disableRedirect();

    // Get the form ID from the request, fallback to add form if not provided
    $form_id = $request->request->get('form_id', 'poll_question_type_add_form');
    // Rebuild the form
    $form = $this->formBuilder->buildForm('\Drupal\poll\Form\PollQuestionTypeAddForm', $form_state);
    // Add the replace command to update the options wrapper
    $response->addCommand(new ReplaceCommand('#multiple-choice-options-wrapper', $form['data']['options']));

    return $response;
  }

}
