<?php
namespace Drupal\poll\Plugin\QuestionType\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Question Type annotation object.
 *
 * @Annotation
 */
class QuestionType extends Plugin
{

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A brief description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
