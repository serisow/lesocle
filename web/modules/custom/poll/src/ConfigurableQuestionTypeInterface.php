<?php
namespace Drupal\poll;

use Drupal\poll\Plugin\QuestionTypeInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the interface for configurable question type.
 *
 * @see \Drupal\poll\Plugin\QuestionType\Annotation\QuestionType
 * @see \Drupal\poll\ConfigurableQuestionTypeBase
 * @see \Drupal\poll\ConfigurableQuestionTypeInterface
 * @see \Drupal\poll\Plugin\QuestionTypeInterface
 * @see \Drupal\poll\QuestionTypeBase
 * @see \Drupal\poll\Plugin\QuestionTypeManager
 * @see plugin_api
 */
interface ConfigurableQuestionTypeInterface extends QuestionTypeInterface, PluginFormInterface
{
}
