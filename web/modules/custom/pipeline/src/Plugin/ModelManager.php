<?php
namespace Drupal\pipeline\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

class ModelManager extends DefaultPluginManager {
  protected $modelNameMap;

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Model',
      $namespaces,
      $module_handler,
      'Drupal\pipeline\Plugin\ModelInterface',
      'Drupal\pipeline\Plugin\Model\Annotation\Model');

    $this->alterInfo('pipeline_model_info');
    $this->setCacheBackend($cache_backend, 'pipeline_model_plugins');
  }

  protected function buildModelNameMap() {
    if (!isset($this->modelNameMap)) {
      $this->modelNameMap = [];
      foreach ($this->getDefinitions() as $plugin_id => $definition) {
        if (isset($definition['model_name'])) {
          $this->modelNameMap[$definition['model_name']] = $plugin_id;
        }
      }
    }
  }

  public function getPluginIdFromModelName($model_name) {
    $this->buildModelNameMap();
    return $this->modelNameMap[$model_name] ?? $model_name;
  }

  public function getModelNameFromPluginId($plugin_id) {
    $definition = $this->getDefinition($plugin_id);
    return $definition['model_name'] ?? $plugin_id;
  }

  public function createInstanceFromModelName($model_name, array $configuration = []) {
    $plugin_id = $this->getPluginIdFromModelName($model_name);
    return $this->createInstance($plugin_id, $configuration);
  }
}
