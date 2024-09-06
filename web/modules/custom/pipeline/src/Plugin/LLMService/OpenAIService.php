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
 *   id = "openai",
 *   label = @Translation("OpenAI Service")
 * )
 */
class OpenAIService  extends PluginBase implements LLMServiceInterface, ContainerFactoryPluginInterface {
  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new OpenAIService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
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


  /**
   * Calls the OpenAI API.
   *
   * @param string $api_url
   *   The OpenAI API URL.
   * @param string $api_key
   *   The OpenAI API key.
   * @param string $prompt
   *   The prompt to send to the API.
   *
   * @return string
   *   The response from the API.
   *
   * @throws \Exception
   */
  public function callOpenAI(string $api_url, string $api_key, string $prompt): string {
    $messages = [
      [
        'role' => 'system',
        'content' => 'You are a helpful assistant.',
      ],
      [
        'role' => 'user',
        'content' => $prompt,
      ],
    ];

    try {
      $response = $this->httpClient->post($api_url, [
        'headers' => [
          'Authorization' => 'Bearer ' . $api_key,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'model' => 'gpt-3.5-turbo',
          'messages' => $messages,
        ],
      ]);

      $content = $response->getBody()->getContents();
      $data = json_decode($content, TRUE);

      if (isset($data['choices'][0]['message']['content'])) {
        return $data['choices'][0]['message']['content'];
      } else {
        throw new \Exception('Unexpected response format from OpenAI API.');
      }
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error calling OpenAI API: @error', ['@error' => $e->getMessage()]);
      throw new \Exception('Failed to call OpenAI API: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function callLLM(array $config, string $prompt): string {
    return $this->callOpenAI($config['api_url'], $config['api_key'], $prompt);
  }
}
