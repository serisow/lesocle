<?php
namespace Drupal\pipeline\Plugin;

interface LLMServiceInterface {
  /**
   * Calls the LLM API.
   *
   * @param array $config
   *   The LLM configuration.
   * @param string $prompt
   *   The prompt to send to the API.
   *
   * @return string
   *   The response from the API.
   */
  public function callLLM(array $config, string $prompt): string;
}
