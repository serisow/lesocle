<?php
namespace Drupal\pipeline\Plugin\Model\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Model item annotation object.
 *
 * @Annotation
 */
class Model extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The service ID this model uses.
   *
   * @var string
   */
  public $service;

  /**
   * The name of the model as used in the API.
   *
   * @var string
   */
  public $model_name;

}
