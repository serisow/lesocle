<?php
namespace Drupal\pipeline;

use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * A collection of step types.
 */
class StepTypePluginCollection extends DefaultLazyPluginCollection {
  /**
   * {@inheritdoc}
   *
   * @return \Drupal\pipeline\Plugin\StepTypeInterface|string
   */
  public function &get($instance_id): string|Plugin\StepTypeInterface
  {
    $result = parent::get($instance_id);
    return $result !== null ? $result : $instance_id;
  }

  /**
   * {@inheritdoc}
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
