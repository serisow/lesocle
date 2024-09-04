<?php

namespace Drupal\poll\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

class LLMAnalysisService
{
  protected $httpClient;
  protected $configFactory;
  protected $logger;

  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, LoggerInterface $logger)
  {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  public function analyzePolls(array $pollData): array
  {
    $prompt = $this->preparePrompt($pollData);
    $response = $this->callLLMApi($prompt);
    return $this->parseResponse($response);
  }

  protected function preparePrompt(array $pollData): string
  {
    $jsonData = json_encode($pollData, JSON_PRETTY_PRINT);
    return "Analyze the following poll data and provide insights suitable for visualization in bar charts, pie charts, and other relevant chart types. Focus on key trends, distributions, and correlations. For each insight, specify the appropriate chart type and the data to be plotted.\n\nPoll Data:\n$jsonData\n\nProvide your analysis in a structured JSON format with the following structure for each insight:\n{\"insight\": \"description\", \"chart_type\": \"type\", \"data\": {}, \"explanation\": \"text\"}";
  }

  protected function callLLMApi(string $prompt): string
  {
    $config = $this->configFactory->get('poll.settings');
    $apiKey = $config->get('openai_api_key');
    $apiUrl = $config->get('openai_api_url');

    $systemPrompt = 'You are an AI assistant specialized in analyzing poll data and providing insights suitable for data visualization. Your responses should be in JSON format, focusing on key trends, distributions, and correlations that can be effectively represented in charts.';

    $messages = [
      [
        'role' => 'system',
        'content' => $systemPrompt,
      ],
      [
        'role' => 'user',
        'content' => $prompt
      ]
    ];

    try {
      $response = $this->httpClient->post($apiUrl, [
        'headers' => [
          'Authorization' => 'Bearer ' . $apiKey,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'model' => 'gpt-4',  // or 'gpt-3.5-turbo' if you prefer
          'messages' => $messages,
        ],
      ]);

      return $response->getBody()->getContents();
    } catch (\Exception $e) {
      $this->logger->error('Error calling OpenAI API: @error', ['@error' => $e->getMessage()]);
      throw new \RuntimeException('Failed to call OpenAI API: ' . $e->getMessage());
    }
  }

  protected function parseResponse(string $response): array
  {
    $data = json_decode($response, true);

    if (isset($data['choices'][0]['message']['content'])) {
      $content = $data['choices'][0]['message']['content'];
      $parsedContent = json_decode($content, true);

      if (json_last_error() === JSON_ERROR_NONE) {
        return $parsedContent;
      } else {
        throw new \RuntimeException('Invalid JSON structure in LLM response.');
      }
    }
    throw new \RuntimeException('Unexpected response format from OpenAI API.');
  }
}
