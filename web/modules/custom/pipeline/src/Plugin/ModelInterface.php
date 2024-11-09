<?php
/**
 * Defines an interface for Model plugins.
 *
 * Model plugins represent specific AI model configurations and capabilities,
 * providing consistent access to model parameters, API endpoints, and default
 * configurations across different providers.
 *
 * Contract requirements:
 * - Must specify model API endpoint
 * - Must define default parameters
 * - Must identify service provider
 * - Must handle model-specific configurations
 * - Must support version management
 *
 * Model patterns:
 * - Must provide consistent parameter structure
 * - Must specify service association
 * - Must define API endpoints
 * - Must handle model versioning
 * - Must support capability exposure
 *
 * Common implementations:
 * - GPT4Model for OpenAI GPT-4
 * - Claude3OpusModel for Anthropic
 * - Gemini15ProModel for Google
 * - DallE3Model for image generation
 *
 * @see \Drupal\pipeline\Plugin\Model\GPT4Model
 * @see \Drupal\pipeline\Plugin\Model\Claude3OpusModel
 * @see \Drupal\pipeline\Plugin\Model\Gemini15ProModel
 */

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
