<?php
namespace Drupal\pipeline\Plugin;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for Model plugins.
 */
abstract class ModelBase extends PluginBase implements ModelInterface {

  /**
   * {@inheritdoc}
   */
  public function getServiceId(): string {
    return $this->pluginDefinition['service'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultParameters(): array {
    return [
      'temperature' => 0.7,
      'max_tokens' => 2048,
      'top_p' => 0.9,
      'frequency_penalty' => 0,
      'presence_penalty' => 0,
      'stop_sequence' => "\n",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string {
    // This should be overridden in child classes if needed.
    return '';
  }

}
