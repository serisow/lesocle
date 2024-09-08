<?php
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
 *     "api_url",
 *     "api_key",
 *     "model_name",
 *     "model_version",
 *     "temperature",
 *     "max_tokens",
 *     "top_p",
 *     "frequency_penalty",
 *     "presence_penalty",
 *     "stop_sequence",
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
  protected $api_url = 'https://api.openai.com/v1';

  /**
   * The API Key.
   *
   * @var string
   */
  protected $api_key = '';

  /**
   * The LLM model name.
   *
   * @var string
   */
  protected $model_name = 'gpt-4';

  /**
   * The LLM model version.
   *
   * @var string
   */
  protected $model_version = 'v1';

  /**
   * The temperature setting for LLM outputs.
   *
   * @var float
   */
  protected $temperature = 0.7;

  /**
   * The maximum number of tokens for the response.
   *
   * @var int
   */
  protected $max_tokens = 1000;

  /**
   * The top-p setting for response diversity.
   *
   * @var float
   */
  protected $top_p = 0.9;

  /**
   * The frequency penalty to control word repetition.
   *
   * @var float
   */
  protected $frequency_penalty = 0.0;

  /**
   * The presence penalty to avoid token repetition.
   *
   * @var float
   */
  protected $presence_penalty = 0.0;

  /**
   * The stop sequence for output termination.
   *
   * @var string
   */
  protected $stop_sequence = "\n";

  /**
   * The LLM service plugin ID.
   *
   * @var string
   */
  protected $service_id = 'openai';

  // Getters and Setters.

  /**
   * Gets the API URL.
   *
   * @return string
   *   The API URL.
   */
  public function getApiUrl() {
    return $this->api_url;
  }

  /**
   * Sets the API URL.
   *
   * @param string $api_url
   *   The API URL.
   */
  public function setApiUrl($api_url) {
    $this->api_url = $api_url;
  }

  /**
   * Gets the API Key.
   *
   * @return string
   *   The API Key.
   */
  public function getApiKey() {
    return $this->api_key;
  }

  /**
   * Sets the API Key.
   *
   * @param string $api_key
   *   The API Key.
   */
  public function setApiKey($api_key) {
    $this->api_key = $api_key;
  }

  /**
   * Gets the model name.
   *
   * @return string
   *   The model name.
   */
  public function getModelName() {
    return $this->model_name;
  }

  /**
   * Sets the model name.
   *
   * @param string $model_name
   *   The model name.
   */
  public function setModelName($model_name) {
    $this->model_name = $model_name;
  }

  /**
   * Gets the model version.
   *
   * @return string
   *   The model version.
   */
  public function getModelVersion() {
    return $this->model_version;
  }

  /**
   * Sets the model version.
   *
   * @param string $model_version
   *   The model version.
   */
  public function setModelVersion($model_version) {
    $this->model_version = $model_version;
  }

  /**
   * Gets the temperature setting.
   *
   * @return float
   *   The temperature setting.
   */
  public function getTemperature() {
    return $this->temperature;
  }

  /**
   * Sets the temperature setting.
   *
   * @param float $temperature
   *   The temperature setting.
   */
  public function setTemperature($temperature) {
    $this->temperature = $temperature;
  }

  /**
   * Gets the max tokens setting.
   *
   * @return int
   *   The max tokens.
   */
  public function getMaxTokens() {
    return $this->max_tokens;
  }

  /**
   * Sets the max tokens setting.
   *
   * @param int $max_tokens
   *   The max tokens.
   */
  public function setMaxTokens($max_tokens) {
    $this->max_tokens = $max_tokens;
  }

  /**
   * Gets the top-p setting.
   *
   * @return float
   *   The top-p setting.
   */
  public function getTopP() {
    return $this->top_p;
  }

  /**
   * Sets the top-p setting.
   *
   * @param float $top_p
   *   The top-p setting.
   */
  public function setTopP($top_p) {
    $this->top_p = $top_p;
  }

  /**
   * Gets the frequency penalty setting.
   *
   * @return float
   *   The frequency penalty.
   */
  public function getFrequencyPenalty() {
    return $this->frequency_penalty;
  }

  /**
   * Sets the frequency penalty setting.
   *
   * @param float $frequency_penalty
   *   The frequency penalty.
   */
  public function setFrequencyPenalty($frequency_penalty) {
    $this->frequency_penalty = $frequency_penalty;
  }

  /**
   * Gets the presence penalty setting.
   *
   * @return float
   *   The presence penalty.
   */
  public function getPresencePenalty() {
    return $this->presence_penalty;
  }

  /**
   * Sets the presence penalty setting.
   *
   * @param float $presence_penalty
   *   The presence penalty.
   */
  public function setPresencePenalty($presence_penalty) {
    $this->presence_penalty = $presence_penalty;
  }

  /**
   * Gets the stop sequence.
   *
   * @return string
   *   The stop sequence.
   */
  public function getStopSequence() {
    return $this->stop_sequence;
  }

  /**
   * Sets the stop sequence.
   *
   * @param string $stop_sequence
   *   The stop sequence.
   */
  public function setStopSequence($stop_sequence) {
    $this->stop_sequence = $stop_sequence;
  }

  public function setServiceId(string $service_id): self {
    $this->service_id = $service_id;
    return $this;
  }

  /**
   * Maps model names to their corresponding service IDs.
   *
   * @var array
   */
  protected static $modelServiceMap = [
    'gpt-3.5-turbo' => 'openai',
    'gpt-4' => 'openai',
    // Add more mappings as needed
  ];

  /**
   * Gets the service ID based on the model name.
   *
   * @return string
   *   The service ID.
   */
  public function getServiceId(): string {
    return self::$modelServiceMap[$this->model_name] ?? 'openai';
  }
}
