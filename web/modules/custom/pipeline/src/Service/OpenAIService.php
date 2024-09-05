<?php
namespace Drupal\pipeline\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class OpenAIService
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
   * Constructs a new OpenAIService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory)
  {
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
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
  public function callOpenAI(string $api_url, string $api_key, string $prompt): string
  {
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
}
