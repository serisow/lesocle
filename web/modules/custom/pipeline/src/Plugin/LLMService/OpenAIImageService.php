<?php
namespace Drupal\pipeline\Plugin\LLMService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\LLMServiceInterface;
use Drupal\pipeline\Service\ImageDownloadService;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\ClientInterface;
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
   * @var \Drupal\pipeline\Service\ImageDownloadService
   */
  protected $imageDownloadService;


  protected $httpClient;
  protected $loggerFactory;

  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    ImageDownloadService $imageDownloadService
  ){
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->imageDownloadService = $imageDownloadService;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('pipeline.image_download_service')
    );
  }

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
            'prompt' => $prompt,
            'n' => 1,
            'size' => $config['image_size'] ?? '1024x1024',
            'response_format' => 'url',
          ],
          'timeout' => 120, // Increased timeout
        ]);

        $content = $response->getBody()->getContents();
        $data = json_decode($content, TRUE);

        if (isset($data['data'][0]['url'])) {
          $imageUrl = $data['data'][0]['url'];
          return $this->imageDownloadService->downloadImage($imageUrl);
        } else {
          throw new \Exception('Unexpected response format from OpenAI Image API.');
        }
      } catch (RequestException $e) {
        $errorDetails = $this->extractErrorDetails($e);

        // Special handling for quota errors
        if ($errorDetails['status_code'] === 429) {
          $this->loggerFactory->get('pipeline')->error('OpenAI Image API quota exceeded: @message', [
            '@message' => $errorDetails['error_message'],
            'error_type' => $errorDetails['error_type'],
            'image_size' => $config['image_size'] ?? '1024x1024',
            'api_url' => $config['api_url'],
          ]);

          throw new \Exception(sprintf(
            'OpenAI Image quota exceeded - Error Type: %s, Message: %s. Please check your billing details.',
            $errorDetails['error_type'],
            $errorDetails['error_message']
          ));
        }

        // Handle other errors
        if ($attempt === $maxRetries) {
          $this->loggerFactory->get('pipeline')->error('OpenAI Image API error after @attempts attempts: @details', [
            '@attempts' => $maxRetries,
            '@details' => json_encode($errorDetails),
            'status_code' => $errorDetails['status_code'],
            'error_type' => $errorDetails['error_type'],
            'image_size' => $config['image_size'] ?? '1024x1024',
          ]);

          throw new \Exception(sprintf(
            'Failed to call OpenAI Image API after %d attempts - Status: %d, Type: %s, Message: %s',
            $maxRetries,
            $errorDetails['status_code'],
            $errorDetails['error_type'],
            $errorDetails['error_message']
          ));
        }

        $this->loggerFactory->get('pipeline')->warning('OpenAI Image API attempt @attempt failed: @message. Retrying in @delay seconds...', [
          '@attempt' => $attempt,
          '@message' => $errorDetails['error_message'],
          '@delay' => $retryDelay,
          'status_code' => $errorDetails['status_code'],
          'error_type' => $errorDetails['error_type'],
        ]);

        // Log and wait before retrying
        sleep($retryDelay);
      }

    }
    throw new \Exception('Failed to call OpenAI Image API after exhausting all retry attempts.');
  }

  public function callLLM(array $config, string $prompt): string {
    return $this->callOpenAIImage($config, $prompt);
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
