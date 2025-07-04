<?php
/**
 * Defines the Pipeline configuration entity.
 *
 * A Pipeline is a configurable sequence of steps that can be executed either on
 * demand or on a schedule. Each step in the pipeline is a plugin instance that
 * performs a specific operation (LLM calls, actions, etc.).
 *
 * Key features:
 * - Stores ordered collection of step type plugins
 * - Supports scheduling (one-time and recurring)
 * - Tracks execution failures and auto-disables after threshold
 * - Maintains execution history
 *
 * Important behaviors:
 * - Steps are executed in weight order
 * - Pipeline auto-disables after 3 consecutive failures
 * - Each step can access results from previous steps
 * - Supports both Drupal-side and Go-service execution
 *
 * @ConfigEntityType(
 *   id = "pipeline",
 *   label = @Translation("Pipeline"),
 *   handlers = {...}
 * )
 *
 * @see \Drupal\pipeline\Entity\PipelineInterface
 * @see \Drupal\pipeline\Plugin\StepTypeInterface
 */

namespace Drupal\pipeline\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\pipeline\StepTypePluginCollection;
use Drupal\pipeline\Plugin\StepTypeInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Defines the Pipeline entity.
 *
 * @ConfigEntityType(
 *   id = "pipeline",
 *   label = @Translation("Pipeline"),
 *   handlers = {
 *     "list_builder" = "Drupal\pipeline\PipelineListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pipeline\Form\PipelineAddForm",
 *       "edit" = "Drupal\pipeline\Form\PipelineEditForm",
 *       "delete" = "Drupal\pipeline\Form\PipelineDeleteForm"
 *     },
 *   },
 *   config_prefix = "pipeline",
 *   admin_permission = "administer pipelines",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status"
 *   },
 *   links = {
 *     "collection" = "/admin/structure/pipelines",
 *     "add-form" = "/admin/structure/pipelines/add",
 *     "edit-form" = "/admin/structure/pipelines/{pipeline}",
 *     "delete-form" = "/admin/structure/pipelines/{pipeline}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "step_types",
 *     "instructions",
 *     "status",
 *     "langcode",
 *     "created",
 *     "changed",
 *     "scheduled_time",
 *     "schedule_type",
 *     "recurring_frequency",
 *     "recurring_time",
 *     "execution_interval",
 *     "execution_type",
 *     "execution_failures",
 *     "entity_type",
 *     "bundle"
 *   }
 * )
 */
class Pipeline extends ConfigEntityBase  implements PipelineInterface, EntityWithPluginCollectionInterface {
  /**
   * The name of the pipeline.
   *
   * @var string
   */
  protected  $id;
  /**
   * The pipeline label.
   *
   * @var string
   */
  protected  $label;
  /**
   * The array of step types for this pipeline.
   *
   * @var array
   */

  protected  $step_types = [];
  /**
   * Holds the collection of step types that are used by this pipeline.
   *
   * @var \Drupal\pipeline\StepTypePluginCollection
   */
  protected  $stepTypesCollection;

  /**
   * Give some informations about the pipeline.
   * @var string
   */
  protected $instructions;

  /**
   * The execution type(the go service can execute scheduled or in-demand pipeline)
   * @var string
   */
  protected $execution_type;

  /**
   * The scheduled execution time of the pipeline.
   *
   * @var int
   */
  protected $scheduled_time;

  /**
   * The execution interval of on-demand scheduled pipeline when they are executed by the cron.
   *
   * @var int
   */

  protected $execution_interval;


  /**
   * The schedule type.
   *
   * @var string
   */
  protected $schedule_type = 'none';

  /**
   * The recurring frequency.
   *
   * @var string
   */
  protected $recurring_frequency;

  /**
   * The recurring time.
   *
   * @var string
   */
  protected $recurring_time;

  /**
   * The pipeline status.
   *
   * @var bool
   */
  protected $status = TRUE;

  /**
   * The pipeline language code.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The time that the pipeline was created.
   *
   * @var int
   */
  protected $created;

  /**
   * The time that the pipeline was last updated.
   *
   * @var int
   */
  protected $changed;

  /**
   * Help keep track the number of failures of the pipeline execution.
   * This prevent from running a failig pipeline due to external technical problem.
   *
   * @var int
   */
  protected $execution_failures = 0;

  /**
   * The entity type this pipeline can be applied to.
   *
   * @var string|null
   */
  protected $entity_type;

  /**
   * The bundle this pipeline can be applied to.
   *
   * @var string|null
   */
  protected $bundle;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteStepType(StepTypeInterface $step_type) {
    $this->getStepTypes()->removeInstanceId($step_type->getUuid());
    $this->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStepType(string $step_type_id) {
    return $this->getStepTypes()->get($step_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getStepTypes() {
    if (!$this->stepTypesCollection) {
      $this->stepTypesCollection = $this->getStepTypesCollection();
      if ($this->stepTypesCollection) {
        $this->stepTypesCollection->sort();
      }
    }
    return $this->stepTypesCollection ?: new StepTypePluginCollection($this->getStepTypeManager(), []);
  }


  /**
   * {@inheritdoc}
   */
  public function getStepTypesCollection() {
    if (empty($this->step_types)) {
      return new StepTypePluginCollection($this->getStepTypeManager(), []);
    }
    return new StepTypePluginCollection($this->getStepTypeManager(), $this->step_types);
  }
  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['step_types' => $this->getStepTypes()];
  }

  /**
   * {@inheritdoc}
   */
  public function addStepType(array $configuration) {
    $configuration['uuid'] = $this->uuidGenerator()->generate();
    $this->step_types[$configuration['uuid']] = $configuration;
    $this->getStepTypes()->addInstanceId($configuration['uuid'], $configuration);
    return $configuration['uuid'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->id = $name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstructions() {
    return $this->instructions;
  }

  /**
   * {@inheritdoc}
   */
  public function setInstructions($instructions) {
    $this->instructions = $instructions;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStepCount() {
    return count($this->getStepTypes());
  }
  /**
   * Returns the step type plugin manager.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The step type plugin manager.
   */
  protected function getStepTypeManager() {
    return \Drupal::service('plugin.manager.step_type');
  }


  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return (bool) $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->status = $status;
    // Reset failures when pipeline is re-enabled
    if ($status) {
      $this->execution_failures = 0;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function setLangcode(string $langcode) {
    $this->langcode = $langcode;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->created;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->created = $timestamp;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->changed;
  }

  /**
   * {@inheritdoc}
   */
  public function setChangedTime($timestamp) {
    $this->changed = $timestamp;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if ($this->isNew()) {
      $this->setCreatedTime(time());
    }
    $this->setChangedTime(time());
  }

  /**
   * {@inheritdoc}
   */
  /**
   * Gets the scheduled execution time.
   *
   * @return int|null
   *   The scheduled execution timestamp, or NULL if not set.
   */
   public function getScheduledTime() {
     return $this->scheduled_time;
   }

  /**
   * Sets the scheduled execution time.
   *
   * @param int $timestamp
   *   The scheduled execution timestamp.
   *
   * @return $this
   */
   public function setScheduledTime($timestamp) {
     $this->scheduled_time = $timestamp;
     return $this;
   }

  /**
   * {@inheritdoc}
   */
  public function getScheduleType() {
    return $this->schedule_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setScheduleType($schedule_type) {
    $this->schedule_type = $schedule_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecurringFrequency() {
    return $this->recurring_frequency;
  }

  /**
   * {@inheritdoc}
   */
  public function setRecurringFrequency($recurring_frequency) {
    $this->recurring_frequency = $recurring_frequency;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecurringTime() {
    return $this->recurring_time;
  }

  /**
   * {@inheritdoc}
   */
  public function setRecurringTime($recurring_time) {
    $this->recurring_time = $recurring_time;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutionInterval() {
    return $this->execution_interval;
  }

  /**
   * {@inheritdoc}
   */
  public function setExecutionInterval($interval) {
    $this->execution_interval = $interval;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutionType() {
    return $this->execution_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setExecutionType($execution_type) {
    $this->execution_type = $execution_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutionFailures() {
    return $this->execution_failures;
  }

  /**
   * {@inheritdoc}
   */
  public function setExecutionFailures($count) {
    $this->execution_failures = $count;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function incrementExecutionFailures() {
    $this->execution_failures++;
    // If we hit the failure threshold, disable the pipeline
    if ($this->execution_failures >= 3) {
      $this->status = FALSE;
      \Drupal::logger('pipeline')->warning('Pipeline %label has been automatically disabled after 3 consecutive failures.', [
        '%label' => $this->label(),
      ]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function resetExecutionFailures() {
    $this->execution_failures = 0;
    return $this;
  }

  /**
   * Gets the entity type this pipeline is applicable to.
   *
   * @return string|null
   *   The entity type ID, or NULL if not set.
   */
  public function getTargetEntityType() {
    return $this->entity_type;
  }

  /**
   * Sets the entity type this pipeline is applicable to.
   *
   * @param string|null $entity_type
   *   The entity type ID.
   *
   * @return $this
   */
  public function setTargetEntityType($entity_type) {
    $this->entity_type = $entity_type;
    return $this;
  }

  /**
   * Gets the bundle this pipeline is applicable to.
   *
   * @return string|null
   *   The bundle, or NULL if not set or not applicable.
   */
  public function getTargetBundle() {
    return $this->bundle;
  }

  /**
   * Sets the bundle this pipeline is applicable to.
   *
   * @param string|null $bundle
   *   The bundle.
   *
   * @return $this
   */
  public function setTargetBundle($bundle) {
    $this->bundle = $bundle;
    return $this;
  }

}
