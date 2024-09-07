<?php
namespace Drupal\pipeline\Plugin\LLMService;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\LLMServiceInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use LLPhant\AnthropicConfig;
use LLPhant\Chat\AnthropicChat;
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
    try {
      $chat = new AnthropicChat(
        new AnthropicConfig(
          $config['model_name'],
          $config['max_tokens'],
          [],
          $config['api_key']
        )
      );
      return $chat->generateText($prompt);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error calling Anthropic API: @error', ['@error' => $e->getMessage()]);
      throw new \Exception('Failed to call Anthropic API: ' . $e->getMessage());
    }
  }

  public function callLLM(array $config, string $prompt): string {
    return $this->callAnthropic($config, $prompt);
  }
}
