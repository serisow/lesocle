<?php
namespace Drupal\pipeline\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

class ActionServiceManager extends DefaultPluginManager {
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ActionService',
      $namespaces,
      $module_handler,
      'Drupal\pipeline\Plugin\ActionServiceInterface',
      'Drupal\pipeline\Plugin\ActionService\Annotation\ActionService'
    );
    $this->alterInfo('action_service_info');
    $this->setCacheBackend($cache_backend, 'action_service_plugins');
  }
}
