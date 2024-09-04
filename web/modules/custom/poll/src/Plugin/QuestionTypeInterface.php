<?php
namespace Drupal\poll\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines an interface for Question type plugins.
 */
interface QuestionTypeInterface extends ConfigurableInterface, PluginInspectionInterface, DependentPluginInterface
{

  /**
   * Returns a render array summarizing the configuration of the question type.
   *
   * @return array
   *   A render array.
   */
  public function getSummary();

  /**
   * Returns the question type label.
   *
   * @return string
   *   The question type label.
   */
  public function label();

  /**
   * Returns the unique ID representing the question type.
   *
   * @return string
   *   The question type ID.
   */
  public function getUuid();

  /**
   * Returns the weight of the question type.
   *
   * @return int|string
   *   Either the integer weight of question type, or an empty string.
   */
  public function getWeight();

  /**
   * Sets the weight for this question type.
   *
   * @param int $weight
   *   The weight for this question type.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Returns the question of the question type.
   *
   * @return int|string
   *   Either the text of question type, or an empty string.
   */
  public function getQuestionText();

  /**
   * Sets the question for this question type.
   *
   * @param string $text
   *   The text for this question type.
   *
   * @return $this
   */
  public function setQuestionText($text);

  /**
   * Check whether a request is an ajax one.
   * @return bool
   */
  public function isAjax(): bool ;

  /**
   * Format the answer for display in the result page.
   * @param $answer
   * @return string
   */
  public function formatAnswer($answer): string;

}
