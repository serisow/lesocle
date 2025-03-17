<?php
namespace Drupal\pipeline\Plugin\LLMService;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
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

    // Check if this is an image generation request based on model name
    $isImageRequest = str_contains($config['model_name'], 'gemini-2.0-flash-exp-image');

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        // Use different handling based on request type
        if ($isImageRequest) {
          return $this->handleImageGeneration($config, $prompt);
        } else {
          // Original text generation logic
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
              'topK' => $config['parameters']['top_k'] ?? 40,
              'topP' => $config['parameters']['top_p'] ?? 0.95,
              'maxOutputTokens' => $config['parameters']['max_tokens'] ?? 8192,
              'responseMimeType' => 'text/plain'
            ]
          ];

          $response = $this->httpClient->post($url, [
            'headers' => [
              'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 4800,
          ]);

          $content = $response->getBody()->getContents();
          $data = json_decode($content, TRUE);

          if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
          } else {
            throw new \Exception('Unexpected response format from Gemini API.');
          }
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

  /**
   * Handles image generation requests for Gemini 2.0 Flash.
   *
   * @param array $config
   *   Configuration settings.
   * @param string $prompt
   *   The prompt for image generation.
   *
   * @return string
   *   JSON encoded file information.
   *
   * @throws \Exception
   *   If the image generation fails.
   */
  protected function handleImageGeneration(array $config, string $prompt): string {
    $api_key = $config['api_key'];
    $api_url = $config['api_url'];

    // Construct payload with all required elements
    $payload = [
      'model' => $config['model_name'],
      'contents' => [
        [
          'role' => 'user',
          'parts' => [
            [
              'text' => $prompt
            ]
          ]
        ]
      ],
      'safetySettings' => [
        [
          'category' => 'HARM_CATEGORY_HATE_SPEECH',
          'threshold' => 'BLOCK_NONE'
        ],
        [
          'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
          'threshold' => 'BLOCK_NONE'
        ],
        [
          'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
          'threshold' => 'BLOCK_NONE'
        ],
        [
          'category' => 'HARM_CATEGORY_HARASSMENT',
          'threshold' => 'BLOCK_NONE'
        ],
        [
          'category' => 'HARM_CATEGORY_CIVIC_INTEGRITY',
          'threshold' => 'BLOCK_NONE'
        ]
      ],
      'generationConfig' => [
        'temperature' => $config['parameters']['temperature'] ?? 1,
        'topP' => $config['parameters']['top_p'] ?? 0.95,
        'topK' => $config['parameters']['top_k'] ?? 40,
        'maxOutputTokens' => $config['parameters']['max_tokens'] ?? 8192,
        'responseMimeType' => 'text/plain',
        'responseModalities' => ['image', 'text']
      ]
    ];

    // Make the request
    $url = "{$api_url}?key={$api_key}";
    $response = $this->httpClient->post($url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'json' => $payload,
      'timeout' => 4800,
    ]);

    $content = $response->getBody()->getContents();
    $data = json_decode($content, TRUE);

    // Handle various response formats
    $this->loggerFactory->get('pipeline')->debug('Gemini image response: @response', ['@response' => json_encode($data)]);

    // Look for image data in different possible locations
    if (!empty($data['candidates'][0]['content']['parts'])) {
      foreach ($data['candidates'][0]['content']['parts'] as $part) {
        if (isset($part['inlineData']) && isset($part['inlineData']['data'])) {
          $imageData = $part['inlineData']['data'];
          return $this->processAndSaveImage($imageData);
        }
        if (isset($part['fileData']) && isset($part['fileData']['fileUri'])) {
          $fileUri = $part['fileData']['fileUri'];
          return $this->downloadAndSaveImage($fileUri, $api_key);
        }
      }
    }

    // If we reached here, no image was found in the response
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
      $textResponse = $data['candidates'][0]['content']['parts'][0]['text'];
      $this->loggerFactory->get('pipeline')->warning('Gemini returned text instead of an image: @text',
        ['@text' => substr($textResponse, 0, 200) . '...']);

      // Return in the same format as an image but with an error flag
      return json_encode([
        'error' => true,
        'message' => 'Received text response instead of image',
        'text_response' => $textResponse,
        'timestamp' => \Drupal::time()->getCurrentTime(),
      ]);
    }

    $responseData = json_encode($data);
    $this->loggerFactory->get('pipeline')->error('No image data found in Gemini API response: @response',
      ['@response' => $responseData]);
    throw new \Exception('No image data found in Gemini API response: ' . $responseData);
  }



  /**
   * Downloads and saves an image from a file URI.
   *
   * @param string $fileUri
   *   The file URI from the Gemini API.
   * @param string $api_key
   *   The API key for authentication.
   *
   * @return string
   *   JSON encoded file information.
   *
   * @throws \Exception
   *   If the download fails.
   */
  protected function downloadAndSaveImage($fileUri, $api_key): string {
    // Construct the download URL
    $downloadUrl = "https://generativelanguage.googleapis.com/v1beta/files/{$fileUri}/content?key={$api_key}";

    // Download the image
    $response = $this->httpClient->get($downloadUrl);
    $imageData = $response->getBody()->getContents();

    // Now save the downloaded image
    return $this->processAndSaveImage($imageData, false); // false = data is not base64 encoded
  }

  /**
   * Process and save image data from Gemini API response.
   *
   * @param string $imageData
   *   Image data, either base64 encoded or raw.
   * @param bool $isBase64
   *   Whether the image data is base64 encoded.
   *
   * @return string
   *   JSON encoded file information.
   *
   * @throws \Exception
   *   If image processing fails.
   */
  protected function processAndSaveImage($imageData, $isBase64 = true) {
    // Decode base64 data if needed
    $decodedImageData = $isBase64 ? base64_decode($imageData) : $imageData;

    // Create directory for storing images
    $directory = 'private://pipeline/images/' . date('Y-m');
    if (!\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
      throw new \Exception('Failed to create directory: ' . $directory);
    }

    // Generate unique filename
    $filename = uniqid('gemini_img_', true) . '.png';
    $uri = $directory . '/' . $filename;

    // Save the file
    $file = \Drupal::service('file.repository')->writeData(
      $decodedImageData,
      $uri,
      FileExists::Replace
    );

    if (!$file) {
      throw new \Exception('Failed to save image file');
    }

    // Set file as permanent
    $file->setPermanent();
    $file->save();

    // Return file information in the same format as other image services
    return json_encode([
      'file_id' => $file->id(),
      'uri' => $file->getFileUri(),
      'url' => $file->createFileUrl(FALSE),
      'mime_type' => 'image/png',
      'filename' => $filename,
      'size' => $file->getSize(),
      'timestamp' => \Drupal::time()->getCurrentTime(),
    ]);
  }
  public function callLLM(array $config, string $prompt): string {
    return $this->callGemini($config, $prompt);
  }
}
