<?php
namespace Drupal\pipeline_integration\EntityCreation;

namespace Drupal\pipeline_integration\EntityCreation;

class EntityCreationStrategyManager {
  protected $strategies = [];

  public function addStrategy(EntityCreationStrategyInterface $strategy) {
    $this->strategies[] = $strategy;
  }
  public function getStrategies(): array {
    return $this->strategies;
  }

  public function getStrategy(string $entityTypeId, string $bundle): ?EntityCreationStrategyInterface {
    foreach ($this->strategies as $strategy) {
      if ($strategy->supportsBundle($entityTypeId, $bundle)) {
        return $strategy;
      }
    }
    return null;
  }

}
