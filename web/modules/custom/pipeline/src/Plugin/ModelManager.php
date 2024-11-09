<?php
/**
 * Plugin manager for LLM Model plugins.
 *
 * Manages the discovery, initialization, and configuration of Language Learning
 * Model (LLM) plugins. This manager handles the complexity of different model
 * versions, providers, and configurations while providing a unified interface
 * for pipeline steps.
 *
 * Core responsibilities:
 * - Model plugin discovery and instantiation
 * - Version compatibility management
 * - Provider configuration mapping
 * - Model capability exposure
 * - Configuration validation
 *
 * Model management:
 * - Handles multiple model versions (GPT-4, Claude, Gemini)
 * - Maps between provider-specific and internal model names
 * - Manages model-specific configurations
 * - Provides capability information
 * - Implements fallback mechanisms
 *
 * Important behaviors:
 * - Maintains model version compatibility
 * - Handles provider-specific configurations
 * - Manages model defaults
 * - Implements caching for plugin information
 * - Provides fallback mechanisms for unavailable models
 *
 * Integration points:
 * - LLM service providers (OpenAI, Anthropic, Google)
 * - Plugin system for model registration
 * - Cache system for plugin information
 * - Configuration system for model settings
 *
 * Key relationships:
 * - Works with ModelInterface implementations
 * - Integrates with LLMServiceManager
 * - Supports ConfigEntity system
 * - Handles cache backend integration
 *
 * @see \Drupal\pipeline\Plugin\ModelInterface
 * @see \Drupal\pipeline\Plugin\Model\GPT4Model
 * @see \Drupal\pipeline\Plugin\Model\Claude3OpusModel
 */

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
