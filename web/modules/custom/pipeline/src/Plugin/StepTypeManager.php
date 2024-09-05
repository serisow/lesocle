<?php
namespace Drupal\pipeline\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager for Step Type plugins.
 */
class StepTypeManager extends DefaultPluginManager {
  /**
   * Constructs a StepTypeManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/StepType',
      $namespaces,
      $module_handler,
      'Drupal\pipeline\Plugin\StepTypeInterface',
      'Drupal\pipeline\Plugin\StepType\Annotation\StepType'
    );
    $this->alterInfo('step_type_info');
    $this->setCacheBackend($cache_backend, 'step_type_plugins');
  }
}
