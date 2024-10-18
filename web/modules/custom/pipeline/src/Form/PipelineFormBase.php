<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\pipeline\Entity\Pipeline;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for pipeline add and edit forms.
 */
abstract class PipelineFormBase extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\pipeline\Entity\PipelineInterface
   */
  protected $entity;

  /**
   * The pipeline entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $pipelineStorage;

  /**
   * Constructs a base class for pipeline add and edit forms.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $pipeline_storage
   *   The pipeline entity storage.
   */
  public function __construct(EntityStorageInterface $pipeline_storage) {
    $this->pipelineStorage = $pipeline_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('pipeline')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pipeline name'),
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this->pipelineStorage, 'load'],
      ],
      '#default_value' => $this->entity->id(),
      '#required' => TRUE,
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pipeline Instructions'),
      '#description' => $this->t('Provide instructions for the pipeline takers.'),
      '#default_value' => $this->entity->getInstructions(),
      '#required' => TRUE,
    ];

    $form['status_container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-item']],
    ];

    $form['status_container']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->isEnabled(),
    ];

    $form['status_container']['status_description'] = [
      '#type' => 'item',
      '#description' => $this->t('Whether the pipeline is enabled or disabled.'),
      '#wrapper_attributes' => ['class' => ['description']],
    ];

    $form['execution_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Execution Type'),
      '#options' => [
        'scheduled' => $this->t('Scheduled'),
        'on_demand' => $this->t('Run on-demand through API CALL'),
      ],
      '#default_value' => $this->entity->get('execution_type') ?? 'scheduled',
      '#description' => $this->t('Choose how this pipeline should be executed.'),
      '#ajax' => [
        'callback' => '::updateExecutionTypeForm',
        'wrapper' => 'execution-settings',
        'event' => 'change',
      ],
    ];

    $form['execution_settings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'execution-settings'],
    ];

    $execution_type = $form_state->getValue('execution_type') ?? $this->entity->get('execution_type') ?? 'scheduled';

    if ($execution_type == 'scheduled') {
      $form['execution_settings']['schedule_type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Schedule Type'),
        '#options' => [
          'none' => $this->t('No schedule'),
          'one_time' => $this->t('One-time schedule'),
          'recurring' => $this->t('Recurring schedule'),
        ],
        '#default_value' => $this->entity->getScheduleType() ?? 'none',
        '#ajax' => [
          'callback' => '::updateScheduleForm',
          'wrapper' => 'schedule-settings',
          'event' => 'change',
        ],
      ];

      $form['execution_settings']['schedule_settings'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'schedule-settings'],
      ];

      $schedule_type = $form_state->getValue(['execution_settings', 'schedule_type']) ?? $this->entity->getScheduleType() ?? 'none';

      if ($schedule_type == 'one_time') {
        $form['execution_settings']['schedule_settings']['one_time'] = [
          '#type' => 'datetime',
          '#title' => $this->t('Schedule Execution'),
          '#default_value' => $this->entity->getScheduledTime() ? DrupalDateTime::createFromTimestamp($this->entity->getScheduledTime()) : NULL,
          '#date_time_element' => 'time',
        ];
      } elseif ($schedule_type == 'recurring') {
        $form['execution_settings']['schedule_settings']['recurring'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Recurring Schedule'),
        ];
        $form['execution_settings']['schedule_settings']['recurring']['frequency'] = [
          '#type' => 'select',
          '#title' => $this->t('Frequency'),
          '#options' => [
            'daily' => $this->t('Daily'),
            'weekly' => $this->t('Weekly'),
            'monthly' => $this->t('Monthly'),
          ],
          '#default_value' => $this->entity->getRecurringFrequency() ?? 'daily',
        ];

        $hours = range(0, 23);
        $minutes = range(0, 59);

        $default_time = $this->entity->getRecurringTime() ?? '00:00';
        list($default_hour, $default_minute) = explode(':', $default_time);

        $form['execution_settings']['schedule_settings']['recurring']['time']['hour'] = [
          '#type' => 'select',
          '#title' => $this->t('Hour'),
          '#options' => array_combine($hours, array_map(function($h) { return sprintf('%02d', $h); }, $hours)),
          '#default_value' => (int) $default_hour,
        ];

        $form['execution_settings']['schedule_settings']['recurring']['time']['minute'] = [
          '#type' => 'select',
          '#title' => $this->t('Minute'),
          '#options' => array_combine($minutes, array_map(function($m) { return sprintf('%02d', $m); }, $minutes)),
          '#default_value' => (int) $default_minute,
        ];
      }
    } else {
      $form['execution_settings']['on_demand_note'] = [
        '#type' => 'item',
        '#markup' => $this->t('This pipeline will be executed manually or through an API call.'),
      ];
    }

    $form['langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Pipeline Language'),
      '#default_value' => $this->entity->getLangcode(),
      '#languages' => LanguageInterface::STATE_ALL,
    ];

    return $form;
  }

  /**
   * Ajax callback to update the execution type form.
   */
  public function updateExecutionTypeForm(array &$form, FormStateInterface $form_state) {
    return $form['execution_settings'];
  }

  /**
   * AJAX callback for updating the schedule form.
   */
  public function updateScheduleForm(array &$form, FormStateInterface $form_state) {
    return $form['execution_settings']['schedule_settings'];
  }


  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // The entity is already saved in submitForm, so we don't need to save it again here
    $this->messenger()->addMessage($this->t('The pipeline %label has been saved.', ['%label' => $this->entity->label()]));
    // Determine which tab we're on
    $route_name = $this->getRouteMatch()->getRouteName();
    if ($route_name == 'entity.pipeline.edit_steps') {
      // If we're on the Steps tab, redirect back to it
      $form_state->setRedirectUrl(Url::fromRoute('entity.pipeline.edit_steps', ['pipeline' => $this->entity->id()]));
    } else {
      // Otherwise, use the default redirect to the edit form
      $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle status update
    $status = $form_state->getValue(['status_container', 'status']);
    $this->entity->setStatus($status);

    // Set the execution type
    $execution_type = $form_state->getValue('execution_type');
    $this->entity->set('execution_type', $execution_type);

    if ($execution_type == 'scheduled') {
      $schedule_type = $form_state->getValue(['execution_settings', 'schedule_type']);
      $this->entity->setScheduleType($schedule_type);

      if ($schedule_type == 'one_time') {
        $scheduled_time = $form_state->getValue(['execution_settings', 'schedule_settings', 'one_time']);
        $this->entity->setScheduledTime($scheduled_time instanceof DrupalDateTime ? $scheduled_time->getTimestamp() : NULL);
        $this->entity->setRecurringFrequency(NULL);
        $this->entity->setRecurringTime(NULL);
      } elseif ($schedule_type == 'recurring') {
        $frequency = $form_state->getValue(['execution_settings', 'schedule_settings', 'recurring', 'frequency']);
        $hour = $form_state->getValue(['execution_settings', 'schedule_settings', 'recurring', 'time', 'hour']);
        $minute = $form_state->getValue(['execution_settings', 'schedule_settings', 'recurring', 'time', 'minute']);
        $time = sprintf('%02d:%02d', $hour, $minute);
        $this->entity->setRecurringFrequency($frequency);
        $this->entity->setRecurringTime($time);
        $this->entity->setScheduledTime(NULL);
      } else {
        // 'none' schedule type
        $this->entity->setScheduledTime(NULL);
        $this->entity->setRecurringFrequency(NULL);
        $this->entity->setRecurringTime(NULL);
      }
    } else {
      // For on-demand pipelines, clear all scheduling information
      $this->entity->setScheduleType(NULL);
      $this->entity->setScheduledTime(NULL);
      $this->entity->setRecurringFrequency(NULL);
      $this->entity->setRecurringTime(NULL);
    }

    parent::submitForm($form, $form_state);

    // Save the entity
    $this->entity->save();
  }


}
