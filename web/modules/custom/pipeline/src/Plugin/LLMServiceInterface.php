<?php
/**
 * Defines the interface for Language Learning Model (LLM) services.
 *
 * LLM services provide standardized access to different AI model providers
 * (OpenAI, Anthropic, Gemini). They handle authentication, API communication,
 * and response processing while providing a consistent interface for pipeline steps.
 *
 * Contract requirements:
 * - Must handle provider-specific authentication
 * - Must support configurable API endpoints
 * - Must implement retry logic for API failures
 * - Must process and validate responses
 * - Must maintain consistent error handling
 *
 * Service patterns:
 * - All API calls must include proper error handling
 * - Services must support model-specific configurations
 * - Must handle rate limiting and quotas
 * - Must validate API responses
 * - Must support timeout configurations
 *
 * Common implementations:
 * - OpenAIService for GPT models
 * - AnthropicService for Claude models
 * - GeminiService for Google's models
 * - OpenAIImageService for DALL-E
 *
 * @see \Drupal\pipeline\Plugin\LLMService\OpenAIService
 * @see \Drupal\pipeline\Plugin\LLMService\AnthropicService
 * @see \Drupal\pipeline\Plugin\LLMService\GeminiService
 */

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
