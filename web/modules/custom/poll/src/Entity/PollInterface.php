<?php
namespace Drupal\poll\Entity;

use Drupal\poll\Plugin\QuestionTypeInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a poll entity.
 */
interface PollInterface extends ConfigEntityInterface {

  /**
   * Returns the poll.
   *
   * @return string
   *   The name of the poll.
   */
  public function getName();

  /**
   * Sets the name of the poll.
   *
   * @param string $name
   *   The name of the poll.
   *
   * @return \Drupal\poll\Entity\PollInterface
   *   The class instance this method is called on.
   */
  public function setName($name);

  /**
   * Returns a specific question type.
   *
   * @param string $question_type_id
   *   The question type ID.
   *
   * @return \Drupal\poll\Plugin\QuestionTypeInterface
   *   The question type object.
   */
  public function getQuestionType(string $question_type_id);

  /**
   * Returns the question types for the poll.
   *
   * The question types should be sorted, and will have been instantiated.
   *
   * @return \Drupal\poll\QuestionTypePluginCollection|\Drupal\poll\Plugin\QuestionTypeInterface[]
   *   The question type plugin collection.
   */
  public function getQuestionTypes();

  /**
   * Returns a question types collection.
   *
   * @return \Drupal\poll\QuestionTypePluginCollection|\Drupal\poll\Plugin\QuestionTypeInterface[]
   *   The question type plugin collection.
   */
  public function getQuestionTypesCollection();

  /**
   * Saves a question type for this poll.
   *
   * @param array $configuration
   *   An array of a question type configuration.
   *
   * @return string
   *   The question type ID.
   */
  public function addQuestionType(array $configuration);

  /**
   * Deletes a question type from this poll.
   *
   * @param \Drupal\poll\Plugin\QuestionTypeInterface $question_type
   *   The question_type object.
   *
   * @return $this
   */
  public function deleteQuestionType(QuestionTypeInterface $question_type);

  /**
   * Gets the poll instructions.
   *
   * @return string
   *   The poll instructions.
   */
  public function getInstructions();

  /**
   * Sets the poll instructions.
   *
   * @param string $instructions
   *   The poll instructions.
   *
   * @return $this
   */
  public function setInstructions($instructions);

  /**
   * Gets the number of questions in the poll.
   *
   * @return int
   *   The number of questions.
   */
  public function getQuestionCount();

  /**
   * @return bool
   *   TRUE if the poll is active, FALSE otherwise.
   */
  public function isActive();

  /**
   * Sets the active status of the poll.
   *
   * @param bool $active
   *   TRUE to set this poll to active, FALSE to set it to inactive.
   *
   * @return $this
   */
  public function setActive($active);

  /**
   * Return the langcode.
   * @return string
   */
  public function getLangcode();

  /**
   * Set the langcode.
   * @param string $langcode
   * @return $this
   */
  public function setLangcode(string $langcode);

  /**
   * Gets the poll creation timestamp.
   *
   * @return int
   *   Creation timestamp of the poll.
   */
  public function getCreatedTime();

  /**
   * Sets the poll creation timestamp.
   *
   * @param int $timestamp
   *   The poll creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the poll changed timestamp.
   *
   * @return int
   *   Changed timestamp of the poll.
   */
  public function getChangedTime();

  /**
   * Sets the poll changed timestamp.
   *
   * @param int $timestamp
   *   The poll changed timestamp.
   *
   * @return $this
   */
  public function setChangedTime($timestamp);

  /**
   * Checks if the poll is closed.
   *
   * @return bool
   *   TRUE if the poll is closed, FALSE otherwise.
   */
  public function isClosed();

  /**
   * Closes the poll.
   *
   * @return $this
   */
  public function close();

  /**
   * Sets the LLM analysis results.
   *
   * @param array $analysis
   *   The LLM analysis results.
   *
   * @return $this
   */
  public function setLlmAnalysis(array $analysis);

  /**
   * Gets the LLM analysis results.
   *
   * @return array
   *   The LLM analysis results.
   */
  public function getLlmAnalysis();

  /**
   * Gets the date the poll was closed.
   *
   * @return int|null
   *   The timestamp when the poll was closed, or NULL if not closed.
   */
  public function getClosedDate();

  /**
   * Sets the date the poll was closed.
   *
   * @param int $timestamp
   *   The timestamp when the poll was closed.
   *
   * @return $this
   */
  public function setClosedDate($timestamp);

  /**
   * Gets the status of the poll.
   *
   * @return string
   *   The status of the poll (active, inactive, or closed).
   */
  public function getStatus();

  /**
   * Sets the status of the poll.
   *
   * @param string $status
   *   The status to set (use class constants).
   *
   * @return $this
   */
  public function setStatus($status);
}
