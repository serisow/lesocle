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
 *   id = "openai_image",
 *   label = @Translation("OpenAI Image Service")
 * )
 */
class OpenAIImageService extends PluginBase implements LLMServiceInterface, ContainerFactoryPluginInterface
{
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
   * Constructs a new OpenAIImageService object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('logger.factory')
    );
  }

  /**
   * Calls the OpenAI Image API.
   *
   * @param array $config
   *   The configuration array containing API details.
   * @param string $prompt
   *   The prompt to generate an image from.
   *
   * @return string
   *   The URL of the generated image.
   *
   * @throws \Exception
   */
  public function callOpenAIImage(array $config, string $prompt): string
  {
    $maxRetries = 3;
    $retryDelay = 5;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        $response = $this->httpClient->post($config['api_url'], [
          'headers' => [
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'model' => $config['model_name'],
            'prompt' => $prompt,
            'n' => 1,
            'size' => $config['image_size'] ?? '1024x1024',
            'response_format' => 'url',
          ],
          'timeout' => 480, // Increased timeout
        ]);

        $content = $response->getBody()->getContents();
        $data = json_decode($content, TRUE);

        if (isset($data['data'][0]['url'])) {
          return $data['data'][0]['url'];
        } else {
          throw new \Exception('Unexpected response format from OpenAI Image API.');
        }
      } catch (RequestException $e) {
        if ($attempt === $maxRetries) {
          $this->loggerFactory->get('pipeline')->error('Error calling OpenAI Image API after ' . $maxRetries . ' attempts: @error', ['@error' => $e->getMessage()]);
          throw new \Exception('Failed to call OpenAI Image API after multiple attempts: ' . $e->getMessage());
        }
        $this->loggerFactory->get('pipeline')->warning('Attempt ' . $attempt . ' failed. Retrying in ' . $retryDelay . ' seconds...');
        sleep($retryDelay);
      }
    }
    throw new \Exception('Failed to call OpenAI Image API after exhausting all retry attempts.');
  }

  /**
   * {@inheritdoc}
   */
  public function callLLM(array $config, string $prompt): string
  {
    return $this->callOpenAIImage($config, $prompt);
  }
}
