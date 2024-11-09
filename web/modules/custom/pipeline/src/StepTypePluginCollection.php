<?php
/**
 * A collection of step types.
 *
 * This collection manages step type plugins for a pipeline entity. It provides:
 * - Lazy loading of step type plugins
 * - Sorting of steps by weight
 * - Fallback handling for missing plugins
 *
 * Used by Pipeline entity to store and manage its step type plugins in a way that
 * integrates with Drupal's configuration system.
 *
 * @see \Drupal\pipeline\Entity\Pipeline
 * @see \Drupal\Core\Plugin\DefaultLazyPluginCollection
 */
namespace Drupal\pipeline;

use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * A collection of step types.
 */
class StepTypePluginCollection extends DefaultLazyPluginCollection {
  /**
   * {@inheritdoc}
   *
   * Provides fallback handling - returns instance ID if plugin not found.
   *
   * @param string $instance_id
   *   The step type plugin instance ID.
   *
   * @return \Drupal\pipeline\Plugin\StepTypeInterface|string
   *   The plugin instance or the instance ID if plugin not found.
   */
  public function &get($instance_id): string|Plugin\StepTypeInterface
  {
    $result = parent::get($instance_id);
    return $result !== null ? $result : $instance_id;
  }

  /**
   * {@inheritdoc}
   *
   * Helper method for sorting step types by weight.
   */
  public function sortHelper($aID, $bID) {
    $a_weight = $this->get($aID)->getWeight();
    $b_weight = $this->get($bID)->getWeight();
    if ($a_weight == $b_weight) {
      return 0;
    }
    return ($a_weight < $b_weight) ? -1 : 1;
  }
}
