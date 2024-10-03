<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\SubformState;
use Drupal\pipeline\ConfigurableStepTypeInterface;
use Drupal\pipeline\Entity\PipelineInterface;
use Drupal\pipeline\Plugin\StepTypeInterface;
use Drupal\pipeline\Plugin\StepTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PipelineStepTypeAddForm extends PipelineStepTypeFormBase {
  /**
   * The step type manager.
   *
   * @var \Drupal\pipeline\Plugin\StepTypeManager
   */
  protected $stepTypeManager;


  protected $formBuilder;

  /**
   * Constructs a new PipelineStepTypeAddForm.
   *
   * @param \Drupal\pipeline\Plugin\StepTypeManager $step_type_manager
   *   The step type manager.
   */
  public function __construct(StepTypeManager $step_type_manager, FormBuilder $form_builder) {
    $this->stepTypeManager = $step_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.step_type'),
      $container->get('form_builder')
    );
  }

  public function getFormId() {
    return 'pipeline_step_type_add_form';
  }

  public static function getTitle(PipelineInterface $pipeline = NULL, $step_type = NULL) {
    $step_type_manager = \Drupal::service('plugin.manager.step_type');
    $plugin_definition = $step_type_manager->getDefinition($step_type);
    $label = $plugin_definition['label'];
    return t('Add New @type Step', ['@type' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PipelineInterface $pipeline = NULL, $step_type = NULL, $uuid = NULL) {
    $pipeline = $form_state->get('pipeline');
    $step_type = $form_state->get('step_type');
    // Get the step type plugin instance
    $this->stepType = $this->stepTypeManager->createInstance($step_type);

    // Check if step_description is in the form_state values
    $step_description = $form_state->getValue(['data', 'step_description']);
    if ($step_description === NULL && $this->stepType instanceof StepTypeInterface) {
      $step_description = $this->stepType->getStepDescription();
      if ($step_description !== null) {
        $form_state->setValue(['data', 'step_description'], $step_description);
      }
    }

    $form = parent::buildForm($form, $form_state, $pipeline, $this->stepType);

    // Check if the form is being loaded in a modal
    if ($this->getRequest()->query->get('modal')) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
      ];
    }


    $form['#title'] = $this->t('Add %label step type', ['%label' => $this->stepType->label()]);
    $form['actions']['submit']['#value'] = $this->t('Add step type');
    //$form['#action'] = $pipeline->toUrl('edit-form')->toString();

    // Add a wrapper for AJAX replacement
    $form['#prefix'] = '<div id="step-type-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['data-dialog-form'] = 'true';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Call the step type plugin's validate method
    if ($this->stepType instanceof ConfigurableStepTypeInterface) {
      $this->stepType->validateConfigurationForm($form['data'], SubformState::createForSubform($form['data'], $form, $form_state));
    }
  }
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    // The form is already processed in the controller, so we just need to return NULL
    // to prevent Drupal from trying to generate an AJAX response here
    return NULL;
  }

}
