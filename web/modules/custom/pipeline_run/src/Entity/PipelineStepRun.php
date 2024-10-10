<?php
namespace Drupal\pipeline_run\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Pipeline Step Run entity.
 *
 * @ContentEntityType(
 *   id = "pipeline_step_run",
 *   label = @Translation("Pipeline Step Run"),
 *   label_collection = @Translation("Pipeline Step Runs"),
 *   label_singular = @Translation("pipeline step run"),
 *   label_plural = @Translation("pipeline step runs"),
 *   base_table = "pipeline_step_run",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "id"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\pipeline_run\Controller\PipelineStepRunListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   links = {
 *     "canonical" = "/pipeline_step_run/{pipeline_step_run}",
 *     "delete-form" = "/admin/content/pipeline_step_runs/{pipeline_step_run}/delete",
 *     "collection" = "/admin/content/pipeline_step_runs",
 *   },
 *   admin_permission = "administer pipeline step run",
 * )
 */
class PipelineStepRun extends ContentEntityBase {

  /**
   * Defines the fields for the Pipeline Step Run entity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   An array of base field definitions.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Reference to the Pipeline Run entity.
    $fields['pipeline_run_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Pipeline Run'))
      ->setDescription(t('The pipeline run this step run is associated with.'))
      ->setSetting('target_type', 'pipeline_run')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ]);

    // The UUID of the step in the pipeline configuration.
    $fields['step_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Step UUID'))
      ->setDescription(t('The UUID of the step from the pipeline configuration.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 128,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 1,
      ]);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The final status of the step execution.'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          'success' => 'Success',
          'failed' => 'Failed',
        ],
      ])
      ->setDefaultValue('success')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 2,
      ]);

    // The output data from the step execution.
    $fields['output'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Output'))
      ->setDescription(t('The output data from the step execution.'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_long',
        'weight' => 3,
      ]);

    // The error message if the step failed.
    $fields['error_message'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Error Message'))
      ->setDescription(t('The error message if the step failed.'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_long',
        'weight' => 4,
      ]);

    // The execution time of the step in seconds.
    $fields['execution_time'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Execution Time'))
      ->setDescription(t('Time taken to execute the step, in seconds.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_float',
        'weight' => 5,
      ]);

    // The sequence/order of the step in the pipeline.
    $fields['sequence'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Sequence'))
      ->setDescription(t('The order of the step in the pipeline.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 6,
      ]);

    // Timestamp when the step started.
    $fields['start_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Start Time'))
      ->setDescription(t('The time when the step run started.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 7,
      ]);

    // Timestamp when the step ended.
    $fields['end_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('End Time'))
      ->setDescription(t('The time when the step run ended.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 8,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 8,
      ]);

    $fields['step_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Step Type'))
      ->setDescription(t('The type of the pipeline step.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 9,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 9,
      ]);

    return $fields;
  }

  /**
   * Gets the Pipeline Run ID.
   *
   * @return int
   *   The Pipeline Run ID.
   */
  public function getPipelineRunId() {
    return $this->get('pipeline_run_id')->target_id;
  }

  /**
   * Sets the Pipeline Run ID.
   *
   * @param int $pipeline_run_id
   *   The Pipeline Run ID.
   *
   * @return $this
   */
  public function setPipelineRunId($pipeline_run_id) {
    $this->set('pipeline_run_id', $pipeline_run_id);
    return $this;
  }

  /**
   * Gets the Step UUID.
   *
   * @return string
   *   The Step UUID.
   */
  public function getStepUuid() {
    return $this->get('step_uuid')->value;
  }

  /**
   * Sets the Step UUID.
   *
   * @param string $step_uuid
   *   The Step UUID.
   *
   * @return $this
   */
  public function setStepUuid($step_uuid) {
    $this->set('step_uuid', $step_uuid);
    return $this;
  }

  /**
   * Gets the status.
   *
   * @return string
   *   The status.
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * Sets the status.
   *
   * @param string $status
   *   The status.
   *
   * @return $this
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * Gets the output.
   *
   * @return string
   *   The output data.
   */
  public function getOutput() {
    return $this->get('output')->value;
  }

  /**
   * Sets the output.
   *
   * @param string $output
   *   The output data.
   *
   * @return $this
   */
  public function setOutput($output) {
    $this->set('output', $output);
    return $this;
  }

  /**
   * Gets the error message.
   *
   * @return string
   *   The error message.
   */
  public function getErrorMessage() {
    return $this->get('error_message')->value;
  }

  /**
   * Sets the error message.
   *
   * @param string $error_message
   *   The error message.
   *
   * @return $this
   */
  public function setErrorMessage($error_message) {
    $this->set('error_message', $error_message);
    return $this;
  }

  /**
   * Gets the execution time.
   *
   * @return float
   *   The execution time in seconds.
   */
  public function getExecutionTime() {
    return $this->get('execution_time')->value;
  }

  /**
   * Sets the execution time.
   *
   * @param float $execution_time
   *   The execution time in seconds.
   *
   * @return $this
   */
  public function setExecutionTime($execution_time) {
    $this->set('execution_time', $execution_time);
    return $this;
  }

  /**
   * Gets the sequence.
   *
   * @return int
   *   The sequence number.
   */
  public function getSequence() {
    return $this->get('sequence')->value;
  }

  /**
   * Sets the sequence.
   *
   * @param int $sequence
   *   The sequence number.
   *
   * @return $this
   */
  public function setSequence($sequence) {
    $this->set('sequence', $sequence);
    return $this;
  }

  /**
   * Gets the start time.
   *
   * @return int
   *   The start timestamp.
   */
  public function getStartTime() {
    return $this->get('start_time')->value;
  }

  /**
   * Sets the start time.
   *
   * @param int $start_time
   *   The start timestamp.
   *
   * @return $this
   */
  public function setStartTime($start_time) {
    $this->set('start_time', $start_time);
    return $this;
  }

  /**
   * Gets the end time.
   *
   * @return int
   *   The end timestamp.
   */
  public function getEndTime() {
    return $this->get('end_time')->value;
  }

  /**
   * Sets the end time.
   *
   * @param int $end_time
   *   The end timestamp.
   *
   * @return $this
   */
  public function setEndTime($end_time) {
    $this->set('end_time', $end_time);
    return $this;
  }

  public function getDuration() {
    $start = $this->getStartTime();
    $end = $this->getEndTime();
    return $end && $start ? $end - $start : NULL;
  }

  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if ($this->getStartTime() && $this->getEndTime()) {
      $this->setExecutionTime($this->getDuration());
    }
  }
}
