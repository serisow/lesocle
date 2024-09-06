<?php
namespace Drupal\pipeline\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

class LLMServiceManager extends DefaultPluginManager {
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/LLMService',
      $namespaces,
      $module_handler,
      'Drupal\pipeline\Plugin\LLMServiceInterface',
      'Drupal\pipeline\Plugin\LLMService\Annotation\LLMService'
    );
    $this->alterInfo('llm_service_info');
    $this->setCacheBackend($cache_backend, 'llm_service_plugins');
  }
}
