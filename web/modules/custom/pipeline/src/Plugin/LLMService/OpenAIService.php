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
   * @param array $config
   *   The LLM Config.
   * @param string $prompt
   *   The prompt to send to the API.
   *
   * @return string
   *   The response from the API.
   *
   * @throws \Exception
   */

  public function callOpenAI(array $config, string $prompt): string {
    $maxRetries = 3;
    $retryDelay = 5;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        $messages = [
          [
            'role' => 'user',
            'content' => $prompt,
          ],
        ];

        $response = $this->httpClient->post($config['api_url'], [
          'headers' => [
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'model' => $config['model_name'],
            'messages' => $messages,
          ],
          'timeout' => 120, // Increased timeout
        ]);

        $content = $response->getBody()->getContents();
        $data = json_decode($content, TRUE);

        if (isset($data['choices'][0]['message']['content'])) {
          return $data['choices'][0]['message']['content'];
        } else {
          throw new \Exception('Unexpected response format from OpenAI API.');
        }
      } catch (RequestException $e) {
        $errorDetails = $this->extractErrorDetails($e);

        // Special handling for quota errors
        if ($errorDetails['status_code'] === 429) {
          $this->loggerFactory->get('pipeline')->error('OpenAI API quota exceeded: @message', [
            '@message' => $errorDetails['error_message'],
            'error_type' => $errorDetails['error_type'],
            'model' => $config['model_name'],
            'api_url' => $config['api_url'],
          ]);

          throw new \Exception(sprintf(
            'OpenAI quota exceeded - Error Type: %s, Message: %s. Please check your billing details.',
            $errorDetails['error_type'],
            $errorDetails['error_message']
          ));
        }

        if ($attempt === $maxRetries) {
          $this->loggerFactory->get('pipeline')->error('OpenAI API error after @attempts attempts: @details', [
            '@attempts' => $maxRetries,
            '@details' => json_encode($errorDetails),
            'status_code' => $errorDetails['status_code'],
            'error_type' => $errorDetails['error_type'],
            'model' => $config['model_name'],
          ]);

          throw new \Exception(sprintf(
            'Failed to call OpenAI API after %d attempts - Status: %d, Type: %s, Message: %s',
            $maxRetries,
            $errorDetails['status_code'],
            $errorDetails['error_type'],
            $errorDetails['error_message']
          ));
        }

        $this->loggerFactory->get('pipeline')->warning('OpenAI API attempt @attempt failed: @message. Retrying in @delay seconds...', [
          '@attempt' => $attempt,
          '@message' => $errorDetails['error_message'],
          '@delay' => $retryDelay,
          'status_code' => $errorDetails['status_code'],
          'error_type' => $errorDetails['error_type'],
        ]);

        sleep($retryDelay);
      }
    }
    // Add this line at the end of the function
    throw new \Exception('Failed to call OpenAI API after exhausting all retry attempts.');
  }

  /**
   * {@inheritdoc}
   *
   */
  public function callLLM(array $config, string $prompt): string {
    return $this->callOpenAI($config, $prompt);
  }

  /**
   * Extracts detailed error information from OpenAI API response.
   */
  protected function extractErrorDetails(RequestException $e): array {
    $response = $e->getResponse();
    $statusCode = $response ? $response->getStatusCode() : 0;
    $body = '';
    $errorType = '';
    $errorMessage = '';

    if ($response) {
      $body = $response->getBody()->getContents();
      $errorData = json_decode($body, TRUE);
      if (isset($errorData['error'])) {
        $errorType = $errorData['error']['type'] ?? '';
        $errorMessage = $errorData['error']['message'] ?? '';
      }
    }

    return [
      'status_code' => $statusCode,
      'error_type' => $errorType,
      'error_message' => $errorMessage,
      'raw_body' => $body,
    ];
  }

}
