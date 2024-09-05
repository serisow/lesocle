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
   * @return bool
   *   TRUE if the pipeline is active, FALSE otherwise.
   */
  public function isActive();

  /**
   * Sets the active status of the pipeline.
   *
   * @param bool $active
   *   TRUE to set this pipeline to active, FALSE to set it to inactive.
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
   * Gets the status of the pipeline.
   *
   * @return string
   *   The status of the pipeline (active, inactive, or closed).
   */
  public function getStatus();

  /**
   * Sets the status of the pipeline.
   *
   * @param string $status
   *   The status to set (use class constants).
   *
   * @return $this
   */
  public function setStatus($status);
}
