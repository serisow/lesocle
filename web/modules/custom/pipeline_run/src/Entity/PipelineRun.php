<?php
namespace Drupal\pipeline_run\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * @ContentEntityType(
 *   id = "pipeline_run",
 *   label = @Translation("Pipeline Run"),
 *   label_collection = @Translation("Pipeline Runs"),
 *   label_singular = @Translation("pipeline run"),
 *   label_plural = @Translation("pipeline runs"),
 *   base_table = "pipeline_run",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "id"
 *   },
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\pipeline_run\Controller\PipelineRunListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   links = {
 *     "canonical" = "/pipeline_run/{pipeline_run}",
 *     "delete-form" = "/admin/content/pipeline_runs/manage/{pipeline_run}/delete",
 *     "collection" = "/admin/content/pipeline_runs",
 *   },
 *   admin_permission = "administer pipeline run",
 * )
 */
class PipelineRun extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['pipeline_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Pipeline'))
      ->setDescription(t('The pipeline this run is associated with.'))
      ->setSetting('target_type', 'pipeline')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ]);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The current status of the pipeline run.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'pending' => 'Pending',
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
      ])
      ->setDefaultValue('pending')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 1,
      ]);

    $fields['start_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Start Time'))
      ->setDescription(t('The time when the pipeline run started.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 2,
      ]);

    $fields['end_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('End Time'))
      ->setDescription(t('The time when the pipeline run ended.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 3,
      ]);

    $fields['duration'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Duration'))
      ->setDescription(t('The duration of the pipeline run in seconds.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['step_results'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Step Results'))
      ->setDescription(t('Serialized data of pipeline step results'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_default',
        'weight' => 5,
      ]);

    $fields['error_message'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Error Message'))
      ->setDescription(t('The error message if the pipeline run failed.'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'text_default',
        'weight' => 6,
      ]);

    $fields['created_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The user who created the pipeline run.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'author',
        'weight' => 7,
      ]);

    $fields['context_data'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Context Data'))
      ->setDescription(t('The context data passed between steps during execution.'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 8,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'text_default',
        'weight' => 8,
      ]);

    $fields['triggered_by'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Triggered By'))
      ->setDescription(t('How this pipeline run was initiated.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'manual' => 'Manual',
        'scheduled' => 'Scheduled',
        'api' => 'API',
      ])
      ->setDefaultValue('manual')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 9,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 9,
      ]);

    return $fields;
  }

  // Getter and setter methods for each field...

  public function getPipelineId() {
    return $this->get('pipeline_id')->target_id;
  }

  public function setPipelineId($pipeline_id) {
    $this->set('pipeline_id', $pipeline_id);
    return $this;
  }

  public function getStatus() {
    return $this->get('status')->value;
  }

  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  public function getStartTime() {
    return $this->get('start_time')->value;
  }

  public function setStartTime($start_time) {
    $this->set('start_time', $start_time);
    return $this;
  }

  public function getEndTime() {
    return $this->get('end_time')->value;
  }

  public function setEndTime($end_time) {
    $this->set('end_time', $end_time);
    return $this;
  }

  public function getDuration() {
    return $this->get('duration')->value;
  }

  public function setDuration($duration) {
    $this->set('duration', $duration);
    return $this;
  }

  public function getStepResults() {
    return $this->get('step_results')->value;
  }

  public function setStepResults($step_results) {
    $this->set('step_results', $step_results);
    return $this;
  }
  public function getErrorMessage() {
    return $this->get('error_message')->value;
  }

  public function setErrorMessage($error_message) {
    $this->set('error_message', $error_message);
    return $this;
  }

  public function getCreatedBy() {
    return $this->get('created_by')->entity;
  }

  public function setCreatedBy($user) {
    $this->set('created_by', $user);
    return $this;
  }

  public function getContextData() {
    return $this->get('context_data')->value;
  }

  public function setContextData($context_data) {
    $this->set('context_data', $context_data);
    return $this;
  }

  public function getTriggeredBy() {
    return $this->get('triggered_by')->value;
  }

  public function setTriggeredBy($triggered_by) {
    $this->set('triggered_by', $triggered_by);
    return $this;
  }

  public function getVersion() {
    return $this->get('version')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $start_time = $this->get('start_time')->value;
    $end_time = $this->get('end_time')->value;

    if ($start_time && $end_time) {
      $duration = $end_time - $start_time;
      $this->set('duration', $duration);
    }
  }
}
