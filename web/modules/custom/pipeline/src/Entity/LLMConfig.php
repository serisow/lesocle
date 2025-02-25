<?php
/**
 * Defines the LLM Configuration entity.
 *
 * This configuration entity stores the connection and authentication settings
 * for Language Learning Model (LLM) services. It provides a reusable way to
 * configure and manage different LLM providers and models.
 *
 * Key features:
 * - Stores API credentials and endpoints
 * - Manages model-specific configurations
 * - Provides parameter management for LLM calls
 * - Supports multiple LLM providers (OpenAI, Anthropic, Gemini)
 *
 * Configuration structure:
 * - API credentials (keys, tokens)
 * - Model selection and version
 * - Default parameters (temperature, tokens, etc.)
 * - Provider-specific settings
 *
 * Usage patterns:
 * - Referenced by LLM steps in pipelines
 * - Used by LLM services for API calls
 * - Provides configuration UI for LLM settings
 *
 * @ConfigEntityType(
 *   id = "llm_config",
 *   label = @Translation("LLM Config"),
 *   ...
 * )
 *
 * @see \Drupal\pipeline\Plugin\LLMServiceInterface
 * @see \Drupal\pipeline\Plugin\ModelInterface
 */

namespace Drupal\pipeline\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the LLM Config entity.
 *
 * @ConfigEntityType(
 *   id = "llm_config",
 *   label = @Translation("LLM Config"),
 *   handlers = {
 *     "list_builder" = "Drupal\pipeline\LLMConfigListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pipeline\Form\LLMConfigForm",
 *       "edit" = "Drupal\pipeline\Form\LLMConfigForm",
 *       "delete" = "Drupal\pipeline\Form\LLMConfigDeleteForm"
 *     },
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *   },
 *   config_prefix = "llm_config",
 *   admin_permission = "administer llm config",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "collection" = "/admin/config/llm",
 *     "canonical" = "/admin/config/llm/{llm_config}",
 *     "add-form" = "/admin/config/llm/add",
 *     "edit-form" = "/admin/config/llm/{llm_config}/edit",
 *     "delete-form" = "/admin/config/llm/{llm_config}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "api_key",
 *     "api_secret",
 *     "model_name",
 *     "api_url",
 *     "parameters"
 *   }
 * )
 */
class LLMConfig extends ConfigEntityBase {

  /**
   * The unique ID of the configuration.
   *
   * @var string
   */
  protected $id;

  /**
   * The label of the configuration.
   *
   * @var string
   */
  protected $label;

  /**
   * The API URL.
   *
   * @var string
   */
  protected $api_url;

  /**
   * The API Key.
   *
   * @var string
   */
  protected $api_key;

  /**
   * The API Secret.
   *
   * @var string
   */
  protected $api_secret;

  /**
   * The LLM model name.
   *
   * @var string
   */
  protected $model_name;

  /**
   * The model parameters.
   *
   * @var array
   */
  protected $parameters = [];

  // Add getters and setters for all properties

  public function getModelName() {
    return $this->model_name;
  }

  public function setModelName($model_name) {
    $this->model_name = $model_name;
    return $this;
  }

  public function getApiKey() {
    return $this->api_key;
  }

  public function setApiKey($api_key) {
    $this->api_key = $api_key;
    return $this;
  }

  public function getApiSecret() {
    return $this->api_secret;
  }

  public function setApiSecret($api_secret) {
    $this->api_secret = $api_secret;
    return $this;
  }

  public function getApiUrl() {
    return $this->api_url;
  }

  public function setApiUrl($api_url) {
    $this->api_url = $api_url;
    return $this;
  }

  public function getParameters() {
    return $this->parameters;
  }

  public function setParameters(array $parameters) {
    $this->parameters = $parameters;
    return $this;
  }
}
