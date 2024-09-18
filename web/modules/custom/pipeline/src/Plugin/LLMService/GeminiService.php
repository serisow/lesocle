<?php
namespace Drupal\pipeline\Plugin\LLMService;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\LLMServiceInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LLMService(
 *   id = "gemini",
 *   label = @Translation("Gemini Service")
 * )
 */
class GeminiService extends PluginBase implements LLMServiceInterface, ContainerFactoryPluginInterface {
  protected $httpClient;
  protected $loggerFactory;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('logger.factory')
    );
  }

  public function callGemini(array $config, string $prompt): string {
    $maxRetries = 3;
    $retryDelay = 5;
    $api_url = $config['api_url'];
    $api_key = $config['api_key'];
    $url = "{$api_url}?key={$api_key}";

    $payload = [
      'contents' => [
        [
          'role' => 'user',
          'parts' => [
            ['text' => $prompt]
          ]
        ]
      ],
      'generationConfig' => [
        'temperature' => $config['parameters']['temperature'] ?? 1,
        'topK' => $config['parameters']['top_k'] ?? 64,
        'topP' => $config['parameters']['top_p'] ?? 0.95,
        'maxOutputTokens' => $config['parameters']['max_tokens'] ?? 8192,
        'responseMimeType' => 'text/plain'
      ]
    ];

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        $response = $this->httpClient->post($url, [
          'headers' => [
            'Content-Type' => 'application/json',
          ],
          'json' => $payload,
          'timeout' => 120, // Increased timeout to 120 seconds
        ]);

        $content = $response->getBody()->getContents();
        $data = json_decode($content, TRUE);

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
          return $data['candidates'][0]['content']['parts'][0]['text'];
        } else {
          throw new \Exception('Unexpected response format from Gemini API.');
        }
      } catch (RequestException $e) {
        if ($attempt === $maxRetries) {
          $this->loggerFactory->get('pipeline')->error('Error calling Gemini API after ' . $maxRetries . ' attempts: @error', ['@error' => $e->getMessage()]);
          throw new \Exception('Failed to call Gemini API after multiple attempts: ' . $e->getMessage());
        }
        $this->loggerFactory->get('pipeline')->warning('Attempt ' . $attempt . ' failed. Retrying in ' . $retryDelay . ' seconds...');
        sleep($retryDelay);
      }
    }
    throw new \Exception('Failed to call Gemini API after exhausting all retry attempts.');
  }
  public function callLLM(array $config, string $prompt): string {
    return $this->callGemini($config, $prompt);
  }
}
