<?php
namespace Drupal\pipeline_run\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the Pipeline Run entity edit forms.
 */
class PipelineRunForm extends ContentEntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a PipelineRunForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\pipeline_run\Entity\PipelineRun $pipeline_run */
    $pipeline_run = $this->entity;

    $form['pipeline_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Pipeline'),
      '#target_type' => 'pipeline',
      '#default_value' => $pipeline_run->getPipelineId() ? $this->entityTypeManager->getStorage('pipeline')->load($pipeline_run->getPipelineId()) : NULL,
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'pending' => $this->t('Pending'),
        'running' => $this->t('Running'),
        'completed' => $this->t('Completed'),
        'failed' => $this->t('Failed'),
      ],
      '#default_value' => $pipeline_run->getStatus(),
      '#required' => TRUE,
    ];

    $form['start_time'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Start Time'),
      '#default_value' => $pipeline_run->getStartTime() ? \Drupal::service('date.formatter')->format($pipeline_run->getStartTime(), 'custom', 'Y-m-d H:i:s') : NULL,
    ];

    $form['end_time'] = [
      '#type' => 'datetime',
      '#title' => $this->t('End Time'),
      '#default_value' => $pipeline_run->getEndTime() ? \Drupal::service('date.formatter')->format($pipeline_run->getEndTime(), 'custom', 'Y-m-d H:i:s') : NULL,
    ];

    $form['error_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Error Message'),
      '#default_value' => $pipeline_run->getErrorMessage(),
    ];

    $form['created_by'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Created By'),
      '#target_type' => 'user',
      '#default_value' => $pipeline_run->getCreatedBy(),
    ];

    $form['context_data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Context Data'),
      '#default_value' => $pipeline_run->getContextData(),
      '#description' => $this->t('Enter serialized context data here.'),
    ];

    $form['triggered_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Triggered By'),
      '#options' => [
        'manual' => $this->t('Manual'),
        'scheduled' => $this->t('Scheduled'),
        'api' => $this->t('API'),
      ],
      '#default_value' => $pipeline_run->getTriggeredBy(),
      '#required' => TRUE,
    ];

    $form['log_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Log File'),
      '#upload_location' => 'public://pipeline_run_logs/',
      '#upload_validators' => [
        'file_validate_extensions' => ['log txt'],
      ],
      '#default_value' => $pipeline_run->getLogFile() ? [$pipeline_run->getLogFile()->id()] : NULL,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $pipeline_run = $this->entity;

    $message_args = ['%label' => $pipeline_run->toLink()->toString()];
    $logger_args = [
      '%label' => $pipeline_run->label(),
      'link' => $pipeline_run->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New pipeline run %label has been created.', $message_args));
        $this->logger('pipeline_run')->notice('Created new pipeline run %label', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The pipeline run %label has been updated.', $message_args));
        $this->logger('pipeline_run')->notice('Updated pipeline run %label.', $logger_args);
        break;
    }

    $form_state->setRedirect('entity.pipeline_run.canonical', ['pipeline_run' => $pipeline_run->id()]);

    return $result;
  }

}
