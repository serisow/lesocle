<?php
/**
 * Defines an interface for Step type plugins.
 *
 * Step Type plugins are the fundamental units of pipeline execution, defining
 * discrete operations that can be configured, ordered, and executed within
 * a pipeline. Each step produces output that can be consumed by subsequent steps.
 *
 * Contract requirements:
 * - Each step must have a unique UUID within its pipeline
 * - Steps must support weight-based ordering
 * - Steps must define their output type and key
 * - Steps must be configurable
 * - Steps must be able to process dependencies on previous steps
 *
 * Required behaviors:
 * - Must provide output key for result access
 * - Must define step description
 * - Must support configuration inheritance
 * - Must define output type for proper handling
 *
 * Key relationships:
 * - Used by Pipeline entity for step management
 * - Implemented by StepTypeBase for common functionality
 * - Extended by ConfigurableStepTypeInterface for complex steps
 * - Managed by StepTypeManager for plugin handling
 *
 * Common implementations:
 * - LLMStep for language model interactions
 * - ActionStep for external actions
 * - GoogleSearchStep for search operations
 * - DocumentSearchStep for RAG operations
 *
 * @see \Drupal\pipeline\StepTypeBase
 * @see \Drupal\pipeline\ConfigurableStepTypeInterface
 * @see \Drupal\pipeline\Plugin\StepTypeManager
 */

namespace Drupal\pipeline\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Step type plugins.
 */
interface StepTypeInterface extends ConfigurableInterface, PluginInspectionInterface, DependentPluginInterface
{

  /**
   * Returns a render array summarizing the configuration of the step type.
   *
   * @return array
   *   A render array.
   */
  public function getSummary();

  /**
   * Returns the step type label.
   *
   * @return string
   *   The step type label.
   */
  public function label();

  /**
   * Returns the unique ID representing the step type.
   *
   * @return string
   *   The step type ID.
   */
  public function getUuid();

  /**
   * Returns the weight of the step type.
   *
   * @return int|string
   *   Either the integer weight of step type, or an empty string.
   */
  public function getWeight();

  /**
   * Sets the weight for this step type.
   *
   * @param int $weight
   *   The weight for this step type.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Returns the description of the step type.
   *
   * @return int|string
   *   Either the description of step type, or an empty string.
   */
  public function getStepDescription();

  /**
   * Sets the step description for this step type.
   *
   * @param string $description
   *   The description for this step type.
   *
   * @return $this
   */
  public function setStepDescription($title);

  /**
   * Returns the step output key of the step type.
   *
   * @return string
   *   Either the step output key of step type, or an empty string.
   */
  public function getStepOutputKey() : string;

  /**
   * Returns the step output type of the step type.
   *
   * @return string
   *  The step type output type.
   */
  public function getStepOutputType() : string;

  /**
   * Returns the prompt of the step type.
   *
   * @return string
   *   Either the prompt of step type, or an empty string.
   */
  public function getPrompt() : string;

  /**
   * Check whether a request is an ajax one.
   * @return bool
   */
  public function isAjax(): bool ;

  /**
   * Gets the response of the step type.
   *
   * @return string
   *   The response of the step type.
   */
  public function getResponse(): string;

  /**
   * Sets the response of the step type.
   *
   * @param string $response
   *   The response to set.
   *
   * @return $this
   */
  public function setResponse(string $response);

}
