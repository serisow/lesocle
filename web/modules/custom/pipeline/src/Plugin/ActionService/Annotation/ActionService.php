<?php
namespace Drupal\pipeline\Plugin\ActionService\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Action Service annotation object.
 *
 * @Annotation
 */
class ActionService extends Plugin {
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
}
