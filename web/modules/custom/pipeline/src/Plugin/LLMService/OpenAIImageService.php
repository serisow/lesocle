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
        $response = $e->getResponse();
        $statusCode = $response ? $response->getStatusCode() : null;
        $body = $response ? $response->getBody()->getContents() : '';

        // Log detailed error
        $this->loggerFactory->get('pipeline')->error('OpenAI Image API error: @status @body', [
          '@status' => $statusCode,
          '@body' => $body,
        ]);

        // Determine if we should retry
        $shouldRetry = $statusCode >= 500 || $e->getCode() === 0; // 5xx or network error
        if ($attempt === $maxRetries || !$shouldRetry) {
          throw new \Exception('Failed to call OpenAI Image API: ' . $e->getMessage());
        }

        // Log and wait before retrying
        $this->loggerFactory->get('pipeline')->warning('Attempt ' . $attempt . ' failed with status ' . $statusCode . '. Retrying in ' . $retryDelay . ' seconds...');
        sleep($retryDelay);
      }

    }
    throw new \Exception('Failed to call OpenAI Image API after exhausting all retry attempts.');
  }

  public function callLLM(array $config, string $prompt): string {
    return $this->callOpenAIImage($config, $prompt);
  }
}
