<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\pipeline\Entity\PipelineInterface;
use Drupal\pipeline\Service\PipelineFileManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\pipeline\Plugin\StepTypeManager;

class PipelineStepTypeController extends ControllerBase implements ContainerInjectionInterface {
  /**
   * The step type manager service.
   *
   * @var \Drupal\pipeline\Plugin\StepTypeManager
   */
  protected $stepTypeManager;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The file manager service.
   *
   * @var \Drupal\pipeline\Service\PipelineFileManager
   */
  protected $fileManager;

  /**
   * Constructs a PipelineStepTypeController object.
   *
   * @param \Drupal\pipeline\Plugin\StepTypeManager $step_type_manager
   *   The step type manager service.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder service.
   * @param \Drupal\pipeline\Service\PipelineFileManager $file_manager
   *   The file manager service.
   */
  public function __construct(
    StepTypeManager $step_type_manager,
    FormBuilder $form_builder,
    PipelineFileManager $file_manager
  ) {
    $this->stepTypeManager = $step_type_manager;
    $this->formBuilder = $form_builder;
    $this->fileManager = $file_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.step_type'),
      $container->get('form_builder'),
      $container->get('pipeline.file_manager')
    );
  }

  public function stepTypeAjax(Request $request, PipelineInterface $pipeline) {
    $method = $request->request->get('_method', 'POST');
    $step_type = $request->request->get('step_type');
    $uuid = $request->request->get('uuid');

    $form_id = $request->request->get('form_id');
    if ($method === 'PUT' && $uuid) {
      return $this->updateStepTypeAjax($request, $pipeline, $step_type, $uuid);
    } else {
      return $this->addStepTypeAjax($request, $pipeline, $step_type, $form_id);
    }
  }

  public function addStepTypeAjax(Request $request, PipelineInterface $pipeline, $step_type, $form_id) {
    $response = new AjaxResponse();
    $requestData = $request->request->all();

    if ($this->fileManager->isFileBasedStepType($step_type)) {
      // Process the step type with the uploaded file IDs
      $this->fileManager->processUploadedFiles($requestData, 'add');
    }

    // Build the form state
    $form_state = (new FormState())
      ->setValues($requestData)
      ->set('pipeline', $pipeline)
      ->set('step_type', $step_type);

    // Disable form cache for forms with file uploads
    if ($this->fileManager->isFileBasedStepType($step_type)) {
      // Remove uploaded files from form state to prevent serialization
      $this->fileManager->removeUploadedFilesFromFormState($form_state);
      $form_state->disableCache();
    }
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
      $response->addCommand(new ReplaceCommand('.ui-dialog-content', $form));
      $response->addCommand(new InvokeCommand(NULL, 'showFormErrors', [$errors]));
    } else {
      // If successful, add commands to close the modal and refresh the page
      // No errors, so create and save the step type
      $step_type_instance = $this->stepTypeManager->createInstance($step_type);

      if ($this->fileManager->isFileBasedStepType($step_type)) {
        $cleaned_values = $requestData;
      } else{
        $cleaned_values = $form_state->getValues();
      }
      $step_type_instance->setConfiguration($cleaned_values);
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
      $url = Url::fromRoute('entity.pipeline.edit_steps', ['pipeline' => $pipeline->id()]);
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand($url->toString()));
    }
    return $response;
  }

  /*
   * Handle the click on the submit button: "Update step type" on the modal
   */
  protected function updateStepTypeAjax(Request $request, PipelineInterface $pipeline, $step_type, $uuid) {
    $response = new AjaxResponse();
    try {
      $step_type = $pipeline->getStepType($uuid);

      if (!$step_type) {
        throw new \Exception('Step type not found');
      }
      $requestData = $request->request->all();

      if ($this->fileManager->isFileBasedStepType($step_type->getPluginId())) {
        // Process the step type with the uploaded file IDs
        $this->fileManager->processUploadedFiles($requestData, 'update');
      }

      $form_state = (new FormState())
        ->setValues($requestData)
        ->set('pipeline', $pipeline)
        ->set('step_type', $step_type)
        ->set('uuid', $uuid);

      // Remove uploaded files from form state to prevent serialization
      $this->fileManager->removeUploadedFilesFromFormState($form_state);

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
        if ($this->fileManager->isFileBasedStepType($step_type->getPluginId())) {
          $cleaned_values = $requestData;
        } else{
          $cleaned_values = $form_state->getValues();
        }

        $step_type->setConfiguration($cleaned_values);
        $pipeline->save();
        //@todo inject the service messenger
        \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
        \Drupal::messenger()->addStatus($this->t('Step type updated successfully.'));

        $url = Url::fromRoute('entity.pipeline.edit_steps', ['pipeline' => $pipeline->id()]);
        $response->addCommand(new CloseModalDialogCommand());
        $response->addCommand(new RedirectCommand($url->toString()));
        $response->addCommand(new InvokeCommand(NULL, 'showMessage', ['Step type updated successfully.', 'status']));
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('pipeline')->error('Error updating step type: @error', ['@error' => $e->getMessage()]);
      $response->addCommand(new InvokeCommand(NULL, 'showMessage', ['An error occurred while updating the step type.', 'error']));
    }

    return $response;
  }

  // Handler to update the prompt field used on LLM Step.
  public function updatePrompt(Request $request, PipelineInterface $pipeline, $step_type) {
    $prompt_template_id = $request->request->get('prompt_template_id');
    if ($prompt_template_id) {
      $prompt_template = $this->entityTypeManager()->getStorage('prompt_template')->load($prompt_template_id);
      if ($prompt_template) {
        $prompt = $prompt_template->getTemplate();
        return new JsonResponse(['prompt' => $prompt]);
      }
    }
    return new JsonResponse(['error' => 'Prompt template not found'], 404);
  }

}
