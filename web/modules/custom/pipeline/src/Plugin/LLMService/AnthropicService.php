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
 *   id = "anthropic",
 *   label = @Translation("Anthropic Service")
 * )
 */
class AnthropicService extends PluginBase implements LLMServiceInterface, ContainerFactoryPluginInterface {
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

  public function callAnthropic(array $config, string $prompt): string {
    $maxRetries = 3;
    $retryDelay = 5;
    $api_url = $config['api_url'];

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        $response = $this->httpClient->post($api_url, [
          'headers' => [
            'x-api-key' => $config['api_key'],
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'model' => $config['model_name'],
            'messages' => [
              [
                'role' => 'user',
                'content' => $prompt,
              ],
            ],
            'max_tokens' => (int) $config['max_tokens'] ?? 1000,
          ],
          'timeout' => 120, // Increased timeout to 120 seconds
        ]);

        $content = $response->getBody()->getContents();
        $data = json_decode($content, TRUE);

        if (isset($data['content'][0]['text'])) {
          return $data['content'][0]['text'];
        } else {
          throw new \Exception('Unexpected response format from Anthropic API.');
        }
      } catch (RequestException $e) {
        if ($attempt === $maxRetries) {
          $this->loggerFactory->get('pipeline')->error('Error calling Anthropic API after ' . $maxRetries . ' attempts: @error', ['@error' => $e->getMessage()]);
          throw new \Exception('Failed to call Anthropic API after multiple attempts: ' . $e->getMessage());
        }
        $this->loggerFactory->get('pipeline')->warning('Attempt ' . $attempt . ' failed. Retrying in ' . $retryDelay . ' seconds...');
        sleep($retryDelay);
      }
    }
    throw new \Exception('Failed to call Anthropic API after exhausting all retry attempts.');
  }

  public function callLLM(array $config, string $prompt): string {
    return $this->callAnthropic($config, $prompt);
  }
}
