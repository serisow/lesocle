<?php
namespace Drupal\pipeline\Plugin\StepType\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Step Type annotation object.
 *
 * @Annotation
 */
class StepType extends Plugin
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
