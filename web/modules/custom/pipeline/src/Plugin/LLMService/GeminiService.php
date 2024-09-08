<?php
namespace Drupal\pipeline\Plugin\LLMService;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\LLMServiceInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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

  public function callGemini(string $api_key, string $prompt, array $config): string {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-exp-0827:generateContent?key={$api_key}";

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
        'temperature' => $config['temperature'] ?? 1,
        'topK' => $config['top_k'] ?? 64,
        'topP' => $config['top_p'] ?? 0.95,
        'maxOutputTokens' => $config['max_tokens'] ?? 8192,
        'responseMimeType' => 'text/plain'
      ]
    ];

    try {
      $response = $this->httpClient->post($url, [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json' => $payload,
      ]);

      $content = $response->getBody()->getContents();
      $data = json_decode($content, TRUE);

      if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return $data['candidates'][0]['content']['parts'][0]['text'];
      } else {
        throw new \Exception('Unexpected response format from Gemini API.');
      }
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error calling Gemini API: @error', ['@error' => $e->getMessage()]);
      throw new \Exception('Failed to call Gemini API: ' . $e->getMessage());
    }
  }

  public function callLLM(array $config, string $prompt): string {
    return $this->callGemini($config['api_key'], $prompt, [
      'temperature' => $config['temperature'] ?? 1,
      'top_k' => $config['top_k'] ?? 64,
      'top_p' => $config['top_p'] ?? 0.95,
      'max_tokens' => $config['max_tokens'] ?? 8192,
    ]);
  }
}
