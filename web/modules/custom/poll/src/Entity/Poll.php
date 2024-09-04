<?php
namespace Drupal\poll\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\poll\QuestionTypePluginCollection;
use Drupal\poll\Plugin\QuestionTypeInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Defines the Poll entity.
 *
 * @ConfigEntityType(
 *   id = "poll",
 *   label = @Translation("Poll"),
 *   handlers = {
 *     "list_builder" = "Drupal\poll\PollListBuilder",
 *     "form" = {
 *       "add" = "Drupal\poll\Form\PollAddForm",
 *       "edit" = "Drupal\poll\Form\PollEditForm",
 *       "delete" = "Drupal\poll\Form\PollDeleteForm"
 *     },
 *   },
 *   config_prefix = "poll",
 *   admin_permission = "administer polls",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status"
 *   },
 *   links = {
 *     "collection" = "/admin/structure/polls",
 *     "add-form" = "/admin/structure/polls/add",
 *     "edit-form" = "/admin/structure/polls/{poll}",
 *     "delete-form" = "/admin/structure/polls/{poll}/delete",
 *     "participants" = "/admin/structure/polls/{poll}/participants",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "question_types",
 *     "instructions",
 *     "status",
 *     "langcode",
 *      "created",
 *      "changed",
 *      "llm_analysis",
 *      "closed_date"
 *   }
 * )
 */
class Poll extends ConfigEntityBase  implements PollInterface, EntityWithPluginCollectionInterface {
  /**
   * The name of the poll.
   *
   * @var string
   */
  protected  $id;
  /**
   * The poll label.
   *
   * @var string
   */
  protected  $label;
  /**
   * The array of question types for this poll.
   *
   * @var array
   */

  protected  $question_types = [];
  /**
   * Holds the collection of question types that are used by this poll.
   *
   * @var \Drupal\poll\QuestionTypePluginCollection
   */
  protected  $questionTypesCollection;

  protected $instructions;

  const STATUS_INACTIVE = 'inactive';
  const STATUS_ACTIVE = 'active';
  const STATUS_CLOSED = 'closed';
  /**
   * The status of the poll.
   *
   * @var string
   */
  protected $status = self::STATUS_INACTIVE;

  /**
   * The poll language code.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The time that the poll was created.
   *
   * @var int
   */
  protected $created;

  /**
   * The time that the poll was last updated.
   *
   * @var int
   */
  protected $changed;

  /**
   * The LLM analysis results.
   *
   * @var array
   */
  protected $llm_analysis = [];

  /**
   * The date the poll was closed.
   *
   * @var int
   */
  protected $closed_date;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQuestionType(QuestionTypeInterface $question_type) {
    $this->getQuestionTypes()->removeInstanceId($question_type->getUuid());
    $this->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestionType(string $question_type_id) {
    return $this->getQuestionTypes()->get($question_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestionTypes() {
    if (!$this->questionTypesCollection) {
      $this->questionTypesCollection = $this->getQuestionTypesCollection();
      if ($this->questionTypesCollection) {
        $this->questionTypesCollection->sort();
      }
    }
    return $this->questionTypesCollection ?: new QuestionTypePluginCollection($this->getQuestionTypeManager(), []);
  }


  /**
   * {@inheritdoc}
   */
  public function getQuestionTypesCollection() {
    if (empty($this->question_types)) {
      return new QuestionTypePluginCollection($this->getQuestionTypeManager(), []);
    }
    return new QuestionTypePluginCollection($this->getQuestionTypeManager(), $this->question_types);
  }
  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['question_types' => $this->getQuestionTypes()];
  }

  /**
   * {@inheritdoc}
   */
  public function addQuestionType(array $configuration) {
    $configuration['uuid'] = $this->uuidGenerator()->generate();
    $this->question_types[$configuration['uuid']] = $configuration;
    $this->getQuestionTypes()->addInstanceId($configuration['uuid'], $configuration);
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
  public function getQuestionCount() {
    return count($this->getQuestionTypes());
  }
  /**
   * Returns the question type plugin manager.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The question type plugin manager.
   */
  protected function getQuestionTypeManager() {
    return \Drupal::service('plugin.manager.question_type');
  }

  public function getParticipants() {
    return $this->entityTypeManager()
      ->getStorage('participant')
      ->loadByProperties(['poll' => $this->id()]);
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
    $old_status = $this->status;
    $this->status = $status;

    if ($status === self::STATUS_CLOSED && $old_status !== self::STATUS_CLOSED) {
      $this->closed_date = \Drupal::time()->getRequestTime();
      $this->schedulePollAnalysis();
    } elseif ($status !== self::STATUS_CLOSED) {
      $this->closed_date = NULL;
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
  public function isClosed() {
    return $this->status === self::STATUS_CLOSED;
  }

  /**
   * {@inheritdoc}
   */
  public function close() {
    $this->status = self::STATUS_CLOSED;
    $this->closed_date = \Drupal::time()->getRequestTime();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLlmAnalysis(array $analysis) {
    $this->llm_analysis = $analysis;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLlmAnalysis() {
    return $this->llm_analysis;
  }

  /**
   * {@inheritdoc}
   */
  public function getClosedDate() {
    return $this->closed_date;
  }

  /**
   * {@inheritdoc}
   */
  public function setClosedDate($timestamp) {
    $this->closed_date = $timestamp;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'poll:' . $this->id() . ':participants';
    return $cache_tags;
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
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    foreach ($entities as $entity) {
      // Load all participants associated with this poll
      $participants = \Drupal::entityTypeManager()
        ->getStorage('participant')
        ->loadByProperties(['poll' => $entity->id()]);

      // Delete all associated participants
      if (!empty($participants)) {
        \Drupal::entityTypeManager()
          ->getStorage('participant')
          ->delete($participants);
      }
    }
  }
  public function schedulePollAnalysis() {
    $queue = \Drupal::queue('poll_llm_processing');
    $queue->createItem(['poll_id' => $this->id()]);
  }

}
