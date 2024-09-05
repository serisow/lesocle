<?php
namespace Drupal\pipeline\Controller;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\pipeline\Entity\PipelineInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\pipeline\Plugin\StepTypeManager;

class MultipleChoiceController extends ControllerBase implements ContainerInjectionInterface {
  protected $stepTypeManager;

  protected $formBuilder;

  public function __construct(StepTypeManager $step_type_manager, FormBuilder $form_builder)
  {
    $this->stepTypeManager = $step_type_manager;
    $this->formBuilder = $form_builder;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('plugin.manager.step_type'),
      $container->get('form_builder')
    );
  }

  /**
   * In the modal when a js event is triggered on the input a new option, we hit this handler,
   * to save it and update the modal; this solve a lot of dynamic issues.
   * @param Request $request
   * @param PipelineInterface $pipeline
   * @param $step_type
   * @param $uuid
   * @return AjaxResponse
   */
  public function handleOnInputChange(Request $request, PipelineInterface $pipeline) {
    $method = $request->request->get('_method', 'POST');
    $step_type = $request->request->get('step_type');
    $uuid = $request->request->get('uuid');
    $form_id = $request->request->get('form_id');


    // In the update case
    if ($method === 'PUT' && $uuid) {
      // Case: Updating a step type
      return $this->handleOnInputChangeUpdate($request, $pipeline, $step_type, $uuid);
    } else {
      // Case:  New step type creation
      return $this->handleOnInputChangeNew($request, $pipeline, $step_type, $form_id);
    }
  }

  protected function handleOnInputChangeUpdate(Request $request, PipelineInterface $pipeline, $step_type, $uuid) {
    $response = new AjaxResponse();
    try {
      $step_type = $pipeline->getStepType($uuid);

      if (!$step_type) {
        throw new \Exception('Step type not found');
      }
      $form_state = (new FormState())
        ->setValues($request->request->all())
        ->set('pipeline', $pipeline)
        ->set('step_type', $step_type)
        ->set('uuid', $uuid);

      $form_state->disableRedirect();
      $form = $this->formBuilder->buildForm('Drupal\pipeline\Form\PipelineStepTypeEditForm', $form_state);

      if ($form_state->isValidationComplete() || $form_state->isExecuted()) {
        $this->formBuilder->processForm('Drupal\pipeline\Form\PipelineStepTypeEditForm', $form, $form_state);
      }

      if ($form_state->hasAnyErrors()) {
        $errors = [];
        foreach ($form_state->getErrors() as $name => $error) {
          $errors[$name] = $error->render();
        }
        $form = $this->formBuilder->rebuildForm('Drupal\pipeline\Form\PipelineStepTypeEditForm', $form_state, $form);
        $response->addCommand(new ReplaceCommand('.ui-dialog-content', $form));
        $response->addCommand(new InvokeCommand(NULL, 'showFormErrors', [$errors]));
      } else {
        $step_type->setConfiguration($form_state->getValues());

        $pipeline->save();

        //@todo inject the service messenger
        \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
        \Drupal::messenger()->addStatus($this->t('Step type updated successfully.'));

        $response->addCommand(new ReplaceCommand('#multiple-choice-options-wrapper', $form['data']['options']));
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('pipeline')->error('Error updating step type: @error', ['@error' => $e->getMessage()]);
      $response->addCommand(new InvokeCommand(NULL, 'showMessage', ['An error occurred while updating the step type.', 'error']));
    }
    return $response;
  }

  protected function handleOnInputChangeNew(Request $request, PipelineInterface $pipeline, $step_type, $form_id) {
    $response = new AjaxResponse();
    // Build the form state
    $form_state = (new FormState())
      ->setValues($request->request->all())
      ->set('pipeline', $pipeline)
      ->set('step_type', $step_type);

    // Get the form
    $form_state->disableRedirect();
    $form = $this->formBuilder->buildForm('\Drupal\pipeline\Form\PipelineStepTypeAddForm', $form_state);

    // Validate and submit the form
    if ($form_state->isValidationComplete() || $form_state->isExecuted()) {
      $this->formBuilder->processForm('\Drupal\pipeline\Form\PipelineStepTypeAddForm', $form, $form_state);
    }

    if ($form_state->hasAnyErrors()) {
      $errors = [];
      foreach ($form_state->getErrors() as $name => $error) {
        $errors[$name] = $error->render();
      }
      //$form = $this->formBuilder->rebuildForm('Drupal\pipeline\Form\PipelineStepTypeAddForm', $form_state, $form);
      $response->addCommand(new ReplaceCommand('.ui-dialog-content', $form));
      $response->addCommand(new InvokeCommand(NULL, 'showFormErrors', [$errors]));
    } else {
      // If successful, add commands to close the modal and refresh the page
      // No errors, so create and save the step type
      $step_type_instance = $this->stepTypeManager->createInstance($step_type);
      $step_type_instance->setConfiguration($form_state->getValues());

      // Set the weight to be the last in the list
      $current_step_count = count($pipeline->getStepTypes());
      $step_type_instance->setWeight($current_step_count);
      // Add the step type to the pipeline
      $uuid = $pipeline->addStepType($step_type_instance->getConfiguration());
      //@todo inject the service messenger
      \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
      \Drupal::messenger()->addStatus($this->t('Step type added successfully.'));
      // Save the pipeline entity
      $pipeline->save();

      $response->addCommand(new ReplaceCommand('#multiple-choice-options-wrapper', $form['data']['options']));
    }
    return $response;
  }

  /*
   * @TODO: IVESTIGATE THIS METHOD
 * Handle the click on "Add another option" button
*/
  public function addMultipleChoiceOption(Request $request, PipelineInterface $pipeline) {
    $response = new AjaxResponse();

    $step_type = $request->request->get('step_type');
    $options = json_decode($request->request->get('options', '[]'), true);

    // Add a new empty option
    $options[] = ['text' => ''];

    // Prepare form state for rebuilding the form
    $form_state = new FormState();
    $form_state->setValue(['data', 'options'], $options);
    $form_state->set('pipeline', $pipeline);
    $form_state->set('step_type', $step_type);
    $form_state->disableRedirect();

    // Get the form ID from the request, fallback to add form if not provided
    $form_id = $request->request->get('form_id', 'pipeline_step_type_add_form');
    // Rebuild the form
    $form = $this->formBuilder->buildForm('\Drupal\pipeline\Form\PipelineStepTypeAddForm', $form_state);
    // Add the replace command to update the options wrapper
    $response->addCommand(new ReplaceCommand('#multiple-choice-options-wrapper', $form['data']['options']));

    return $response;
  }

}
