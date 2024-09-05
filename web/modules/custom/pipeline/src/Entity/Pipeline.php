<?php
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
 *      "created",
 *      "changed",
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

  protected $instructions;

  const STATUS_INACTIVE = 'inactive';
  const STATUS_ACTIVE = 'active';

  /**
   * The status of the pipeline.
   *
   * @var string
   */
  protected $status = self::STATUS_INACTIVE;

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
  public function isActive() {
    return $this->status === self::STATUS_ACTIVE;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive($active) {
    $this->status = $active ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
    return $this;
  }

  public function getStatus() {
    return $this->status;
  }
  public function setStatus($status) {
    $this->status = $status;
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



}
