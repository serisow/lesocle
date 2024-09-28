<?php
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
