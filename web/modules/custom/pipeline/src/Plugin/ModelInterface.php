<?php
namespace Drupal\pipeline\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Model plugins.
 */
interface ModelInterface extends PluginInspectionInterface {

  /**
   * Get the default parameters for this model.
   *
   * @return array
   *   An array of default parameters.
   */
  public function getDefaultParameters(): array;

  /**
   * Get the service ID for this model.
   *
   * @return string
   *   The service ID.
   */
  public function getServiceId(): string;

  /**
   * Get the API URL for this model.
   *
   * @return string
   *   The API URL.
   */
  public function getApiUrl(): string;

}
