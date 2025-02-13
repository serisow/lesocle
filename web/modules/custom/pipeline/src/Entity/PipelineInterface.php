<?php
namespace Drupal\pipeline\Entity;

use Drupal\pipeline\Plugin\StepTypeInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a pipeline entity.
 */
interface PipelineInterface extends ConfigEntityInterface {

  /**
   * Returns the pipeline.
   *
   * @return string
   *   The name of the pipeline.
   */
  public function getName();

  /**
   * Sets the name of the pipeline.
   *
   * @param string $name
   *   The name of the pipeline.
   *
   * @return \Drupal\pipeline\Entity\PipelineInterface
   *   The class instance this method is called on.
   */
  public function setName($name);

  /**
   * Returns a specific step type.
   *
   * @param string $step_type_id
   *   The step type ID.
   *
   * @return \Drupal\pipeline\Plugin\StepTypeInterface
   *   The step type object.
   */
  public function getStepType(string $step_type_id);

  /**
   * Returns the step types for the pipeline.
   *
   * The step types should be sorted, and will have been instantiated.
   *
   * @return \Drupal\pipeline\StepTypePluginCollection|\Drupal\pipeline\Plugin\StepTypeInterface[]
   *   The step type plugin collection.
   */
  public function getStepTypes();

  /**
   * Returns a step types collection.
   *
   * @return \Drupal\pipeline\StepTypePluginCollection|\Drupal\pipeline\Plugin\StepTypeInterface[]
   *   The step type plugin collection.
   */
  public function getStepTypesCollection();

  /**
   * Saves a step type for this pipeline.
   *
   * @param array $configuration
   *   An array of a step type configuration.
   *
   * @return string
   *   The step type ID.
   */
  public function addStepType(array $configuration);

  /**
   * Deletes a step type from this pipeline.
   *
   * @param \Drupal\pipeline\Plugin\StepTypeInterface $step_type
   *   The step_type object.
   *
   * @return $this
   */
  public function deleteStepType(StepTypeInterface $step_type);

  /**
   * Gets the pipeline instructions.
   *
   * @return string
   *   The pipeline instructions.
   */
  public function getInstructions();

  /**
   * Sets the pipeline instructions.
   *
   * @param string $instructions
   *   The pipeline instructions.
   *
   * @return $this
   */
  public function setInstructions($instructions);

  /**
   * Gets the number of steps in the pipeline.
   *
   * @return int
   *   The number of steps.
   */
  public function getStepCount();

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
   * Gets the pipeline creation timestamp.
   *
   * @return int
   *   Creation timestamp of the pipeline.
   */
  public function getCreatedTime();

  /**
   * Sets the pipeline creation timestamp.
   *
   * @param int $timestamp
   *   The pipeline creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the pipeline changed timestamp.
   *
   * @return int
   *   Changed timestamp of the pipeline.
   */
  public function getChangedTime();

  /**
   * Sets the pipeline changed timestamp.
   *
   * @param int $timestamp
   *   The pipeline changed timestamp.
   *
   * @return $this
   */
  public function setChangedTime($timestamp);


  /**
   * Returns whether the pipeline is enabled.
   *
   * @return bool
   *   TRUE if the pipeline is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the pipeline status.
   *
   * @param bool $status
   *   TRUE to enable this pipeline, FALSE to disable.
   *
   * @return $this
   */
  public function setStatus($status);

  /**
   * Gets the scheduled execution time.
   *
   * @return int|null
   *   The scheduled execution timestamp, or NULL if not set.
   */
  public function getScheduledTime();

  /**
   * Sets the scheduled execution time.
   *
   * @param int $timestamp
   *   The scheduled execution timestamp.
   *
   * @return $this
   */
  public function setScheduledTime($timestamp);

  /**
   * Gets the scheduled execution interval time for on-demand pipeline.
   *
   * @return int|null
   *   The scheduled execution interval timestamp, or NULL if not set.
   */
  public function getExecutionInterval();

  /**
   * Sets the scheduled execution interval time for on-demand pipeline.
   *
   * @param int $timestamp
   *   The scheduled execution interval time.
   *
   * @return $this
   */
  public function setExecutionInterval($interval);



  /**
   * Gets the schedule type.
   *
   * @return string
   *   The schedule type.
   */
  public function getScheduleType();

  /**
   * Sets the schedule type.
   *
   * @param string $schedule_type
   *   The schedule type.
   *
   * @return $this
   */
  public function setScheduleType($schedule_type);

  /**
   * Gets the recurring frequency.
   *
   * @return string
   *   The recurring frequency.
   */
  public function getRecurringFrequency();

  /**
   * Sets the recurring frequency.
   *
   * @param string $recurring_frequency
   *   The recurring frequency.
   *
   * @return $this
   */
  public function setRecurringFrequency($recurring_frequency);

  /**
   * Gets the recurring time.
   *
   * @return string
   *   The recurring time.
   */
  public function getRecurringTime();

  /**
   * Sets the recurring time.
   *
   * @param string $recurring_time
   *   The recurring time.
   *
   * @return $this
   */
  public function setRecurringTime($recurring_time);

  /**
   * Get the execution type.
   * @return string
   *   The execution type.
   */
  public function getExecutionType();

  /**
   * Set the execution type.
   * @param $execution_type
   *   The execution type.
   *
   * @return $this
   */
  public function setExecutionType($execution_type);

  /**
   * Gets the number of consecutive execution failures.
   *
   * @return int
   *   The number of consecutive execution failures.
   */
  public function getExecutionFailures();

  /**
   * Sets the number of consecutive execution failures.
   *
   * @param int $count
   *   The number of consecutive failures.
   *
   * @return $this
   */
  public function setExecutionFailures($count);

  /**
   * Increments the execution failure count.
   *
   * @return $this
   */
  public function incrementExecutionFailures();

  /**
   * Resets the execution failure count.
   *
   * @return $this
   */
  public function resetExecutionFailures();
}
