<?php
namespace Drupal\pipeline\Plugin\LLMService\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an LLM Service annotation object.
 *
 * @Annotation
 */
class LLMService extends Plugin {
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
