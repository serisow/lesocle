<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\pipeline\ConfigurableStepTypeInterface;
use Drupal\pipeline\Plugin\StepTypeManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Controller for pipeline edit form.
 */
class PipelineEditForm extends PipelineFormBase {
  /**
   * The step type manager service.
   *
   * @var \Drupal\pipeline\Plugin\StepTypeManager
   */
  protected $stepTypeManager;

  protected $formBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;
  /**
   * Constructs an PipelineEditForm object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $pipeline_storage
   *   The storage.
   * @param \Drupal\pipeline\Plugin\StepTypeManager $step_type_manager
   *   The step type manager service.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(
    EntityStorageInterface $pipeline_storage,
    StepTypeManager        $step_type_manager,
    FormBuilder            $form_builder,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($pipeline_storage, $entity_type_manager, $entity_type_bundle_info);
    $this->stepTypeManager = $step_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('pipeline'),
      $container->get('plugin.manager.step_type'),
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  public function getFormId() {
    return 'pipeline_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#title'] = $this->t('Edit pipeline %name', ['%name' => $this->entity->label()]);
    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'pipeline/admin';
    $form['#attached']['library'][] = 'pipeline/step_type_modal';

    $form['#attached']['drupalSettings']['pipelineId'] = $this->entity->id();

    // Determine which tab we're on
    $route_name = $this->getRouteMatch()->getRouteName();
    if ($route_name == 'entity.pipeline.edit_form') {
      // General Information tab
    } elseif ($route_name == 'entity.pipeline.edit_steps') {
      // Steps tab
      // Remove the fields from PipelineFormBase as they're not needed on this tab
      unset(
        $form['label'],
        $form['id'],
        $form['instructions'],
        $form['status_container'],
        $form['langcode'],
        $form['execution_settings'],
        $form['execution_type'],
        $form['application_context']
      );
      // Build the list of existing step types for this pipeline.
      $form['step_types'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('N°'),
          $this->t('Step'),
          $this->t('Step Type'),
          $this->t('Model'),
          $this->t('Weight'),
          $this->t('Operations'),
        ],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'pipeline-step-type-order-weight',
          ],
        ],
        '#attributes' => [
          'id' => 'pipeline-step-types',
          'class' => ['pipeline-steps-table'],
        ],
        '#empty' => $this->t('There are currently no step types in this pipeline. Add one by selecting an option below.'),
        // Render step types below parent elements.
        '#weight' => 5,
      ];

      // Build the new step type addition form and add it to the step type list.
      $new_step_type_options = [];
      $step_types = $this->stepTypeManager->getDefinitions();
      uasort($step_types, function ($a, $b) {
        return strcasecmp($a['id'], $b['id']);
      });
      foreach ($step_types as $step_type => $definition) {
        $new_step_type_options[$step_type] = $definition['label'];
      }

      // Add the "new step" row
      $form['add_step'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['pipeline-new-step-row']],
        '#weight' => 4, // Ensure it's placed just before the step_types table
      ];

      $form['add_step']['step_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Step type'),
        '#title_display' => 'invisible',
        '#options' => $new_step_type_options,
        '#empty_option' => $this->t('- Select a step type -'),
        '#attributes' => ['aria-label' => $this->t('Select a step type')],
      ];

      $form['add_step']['add'] = [
        '#type' => 'button',
        '#value' => $this->t('Add Step'),
        '#ajax' => [
          'callback' => '::openAddStepModal',
          'event' => 'click',
        ],
        '#attributes' => ['class' => ['button--primary']],
      ];

      // Construct the steps rows.
      $step_number = 1;
      foreach ($this->entity->getStepTypes() as $step_type) {
        $uuid = $step_type->getUuid();
        $config = $step_type->getConfiguration();
        $llm_config_id = $config['data']['llm_config'] ?? '';
        /** @var \Drupal\llm_config\Entity\LlmConfigInterface $llm_config */
        $llm_config = $this->entityTypeManager->getStorage('llm_config')->load($llm_config_id);
        $model_name = $llm_config ? $llm_config->getModelName() : 'N/A';

        $form['step_types'][$uuid] = [
          '#attributes' => ['class' => ['draggable']],
          'number' => ['#plain_text' => $step_number],
          'step_description' => ['#plain_text' => $this->trimText($step_type->getConfiguration()['data']['step_description'] ?? '', 50)],
          'step_type' => ['#plain_text' => $step_type->label()],
          'model' => ['#plain_text' => $model_name],
          'weight' => [
            '#type' => 'weight',
            '#title' => $this->t('Weight for @title', ['@title' => $step_type->label()]),
            '#title_display' => 'invisible',
            '#default_value' => $step_type->getWeight(),
            '#attributes' => ['class' => ['pipeline-step-type-order-weight']],
          ],
          'operations' => [
            '#type' => 'operations',
            '#links' => $this->getOperations($step_type),
          ],
        ];
        $step_number++;
      }
    }  elseif ($route_name == 'entity.pipeline.edit_runs') { // TAB for list execution result
      // Runs tab
      $form['runs'] = [
        '#type' => 'view',
        '#name' => 'pipeline_runs',
        '#display_id' => 'embed_1',
        '#arguments' => [$this->entity->id()],
      ];
    }

    $form['#attributes']['data-action-url'] = $this->entity->toUrl('edit-form')->toString();
    return $form;
  }

  public function executePipeline(array &$form, FormStateInterface $form_state) {
    $pipeline = $this->entity;

    // Create PipelineRun entity
    $pipeline_run = \Drupal::entityTypeManager()->getStorage('pipeline_run')->create([
      'pipeline_id' => $pipeline->id(),
      'status' => 'running',
      'start_time' => \Drupal::time()->getCurrentTime(),
      'created_by' => \Drupal::currentUser()->id(),
      'triggered_by' => 'manual',
      'step_results' => json_encode([]),
    ]);
    $pipeline_run->save();
    // Store the pipeline_run_id in the State API
    // We need the pipeline_run_id in finishBatch callback and i do not find a better way of doing it.
    \Drupal::state()->set('pipeline.current_run_id', $pipeline_run->id());
    $pipeline_batch = \Drupal::service('pipeline.batch');

    $batch = [
      'title' => $this->t('Executing Pipeline'),
      'operations' => [],
      'finished' => [$pipeline_batch, 'finishBatch'],
      'progressive' => TRUE,
      'init_message' => $this->t('Starting pipeline execution.'),
      'progress_message' => $this->t('Processed @current out of @total steps.'),
      'error_message' => $this->t('An error occurred during pipeline execution.'),
    ];

    foreach ($pipeline->getStepTypes() as $step_type) {
      $batch['operations'][] = [
        [$pipeline_batch, 'processStep'],
        [$pipeline->id(), $step_type->getUuid()],
      ];
    }

    batch_set($batch);
  }
  public function validateAddStepType(array &$form, FormStateInterface $form_state) {
    $new_step_type = $form_state->getValue(['add_step', 'step_type']);
    if (empty($new_step_type)) {
      $form_state->setErrorByName('add_step][step_type', $this->t('Select a step type to add.'));
    }
  }

  /**
   * Submit handler for step type.
   */
  public function addStepType($form, FormStateInterface $form_state) {
    $new_step_type = $form_state->getValue(['add_step', 'step_type']);
    if (!$new_step_type) {
      $form_state->setErrorByName('add_step][step_type', $this->t('Select a step type to add.'));
      return;
    }
    // Calculate the weight for the new step type
    $weight = count($this->entity->getStepTypes());

    // Check if this field has any configuration options.
    $step_type = $this->stepTypeManager->getDefinition($new_step_type);

    // Load the configuration form for this option.
    if (is_subclass_of($step_type['class'], '\Drupal\pipeline\ConfigurableStepTypeInterface')) {
      $form_state->setRedirect(
        'pipeline.step_type_add_form',
        [
          'pipeline' => $this->entity->id(),
          'step_type' => $new_step_type,
        ],
        ['query' => ['weight' => $weight]]
      );
    } // If there's no form, immediately add the step type.
    else {
      $step_type = [
        'id' => $step_type['id'],
        'data' => [],
        'weight' => $form_state->getValue('weight'),
      ];
      $step_type_id = $this->entity->addStepType($step_type);
      $this->entity->save();
      if (!empty($step_type_id)) {
        $this->messenger()->addMessage($this->t('The Pipeline step type was successfully applied.'));
      }
    }
  }

  public function openAddStepModal(array &$form, FormStateInterface $form_state) {
    $step_type = $form_state->getValue(['add_step', 'step_type']);

    $response = new AjaxResponse();

    if ($step_type) {
      $form_state = (new FormState())
        ->set('pipeline', $this->entity)
        ->set('step_type', $step_type);
      $form = $this->formBuilder->buildForm('Drupal\pipeline\Form\PipelineStepTypeAddForm', $form_state);
      //$form['actions']['submit']['#submit'] = ['::handleSubmit'];

      // Make the dialog title
      $step_type_manager = \Drupal::service('plugin.manager.step_type');
      $plugin_definition = $step_type_manager->getDefinition($step_type);
      $dialog_title = $plugin_definition['label'];


      $response->addCommand(new OpenModalDialogCommand(
        $this->t('Add @type', ['@type' => $dialog_title]),
        $form,
        [
          'width' => 'auto',
          'dialogClass' => 'pipeline-step-type-add-modal',
        ]
      ));
    } else {
      $response->addCommand(new ReplaceCommand('#edit-add-step', $form['add_step']));
    }

    return $response;
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $step_types = $form_state->getValue('step_types');
    if (!empty($step_types)) {
      foreach ($step_types as $uuid => $item) {
        if ($uuid !== 'new' && isset($item['weight'])) {
          $this->entity->getStepType($uuid)->setWeight($item['weight']);
        }
      }
      $this->entity->save();
    }
    // Check if we're on the Steps tab
    $route_name = $this->getRouteMatch()->getRouteName();
    if ($route_name == 'entity.pipeline.edit_steps') {
      // Redirect back to the Steps tab
      $form_state->setRedirect('entity.pipeline.edit_steps', ['pipeline' => $this->entity->id()]);
    } else {
      // Otherwise, use the default redirect (likely the edit form)
      $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $this->messenger()->addMessage($this->t('Changes to the pipeline have been saved.'));
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Update pipeline');
    $actions['execute'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute Pipeline'),
      '#submit' => ['::executePipeline'],
      '#weight' => 5,
    ];
    return $actions;
  }

  /**
   * Trims text to a certain length and adds ellipsis if needed.
   *
   * @param string $text
   *   The text to trim.
   * @param int $length
   *   The maximum length of the trimmed string.
   *
   * @return string
   *   The trimmed text.
   */
  private function trimText($text, $length) {
    if (strlen($text) > $length) {
      return substr($text, 0, $length - 3) . '...';
    }
    return $text;
  }

  protected function getOperations($step_type) {
    $links = [];
    $is_configurable = $step_type instanceof ConfigurableStepTypeInterface;
    if ($is_configurable) {
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('pipeline.step_type_edit_form', [
          'pipeline' => $this->entity->id(),
          'step_type' => $step_type->getPluginId(),
          'uuid' => $step_type->getUuid(),
        ]),
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(
            [
              'width' => 'auto',
              'dialogClass' => 'pipeline-step-type-edit-modal',
            ]),
        ],
      ];
    }
    $links['delete'] = [
      'title' => $this->t('Delete'),
      'url' => Url::fromRoute('pipeline.step_type_delete', [
        'pipeline' => $this->entity->id(),
        'step_type' => $step_type->getUuid(),
      ]),
    ];
    return $links;
  }


}
