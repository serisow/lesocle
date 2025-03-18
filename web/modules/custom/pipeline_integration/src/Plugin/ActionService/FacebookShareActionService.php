<?php
namespace Drupal\pipeline_integration\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "facebook_share",
 *   label = @Translation("Facebook Share Action")
 * )
 */
class FacebookShareActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a FacebookShareActionService object.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ClientInterface $http_client
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration) {
    $form['access_token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Page Access Token'),
      '#default_value' => $configuration['access_token'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter a Facebook Page Access Token (not a user token). The token must have both pages_manage_posts and pages_read_engagement permissions. To get a page token: <ol>
      <li>Go to developers.facebook.com</li>
      <li>Get a User Access Token with pages_manage_posts and pages_read_engagement permissions</li>
      <li>Use the Graph API Explorer to get the Page Access Token</li>
      <li>Use the Debug Token tool to verify it\'s a Page Access Token</li>
    </ol>'),
      '#rows' => 3,
    ];

    $form['page_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facebook Page ID'),
      '#default_value' => $configuration['page_id'] ?? '',
      '#description' => $this->t('The ID of your Facebook page.'),
      '#required' => TRUE,
    ];

    $form['api_version'] = [
      '#type' => 'select',
      '#title' => $this->t('API Version'),
      '#options' => [
        'v22.0' => 'v22.0',
      ],
      '#default_value' => $configuration['api_version'] ?? 'v22.0',
      '#description' => $this->t('Facebook Graph API version.'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    return [
      'access_token' => $form_state->getValue('access_token'),
      'page_id' => $form_state->getValue('page_id'),
      'api_version' => $form_state->getValue('api_version'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      // Validate token before proceeding
      $this->validateAccessToken($config['configuration']);

      $facebook_content = $this->findFacebookContent($context);
      $data = $this->parseAndValidateContent($facebook_content);

      // Choose posting method based on content type
      if (isset($data['image_url'])) {
        return $this->postImage($data, $config['configuration']);
      }
      else {
        return $this->postLink($data, $config['configuration']);
      }

    } catch (RequestException $e) {
      $error = $this->parseErrorResponse($e);
      $this->loggerFactory->get('pipeline')->error('Facebook API error: @error', ['@error' => $error]);
      throw new \Exception("Facebook API Error: " . $error);
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error posting to Facebook: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Validates access token and page access.
   *
   * @throws \Exception
   */
  protected function validateAccessToken(array $config): void {
    try {
      // First verify the page access token by attempting to get basic page info
      $url = sprintf(
        'https://graph.facebook.com/%s/%s',
        $config['api_version'],
        $config['page_id']
      );

      $response = $this->httpClient->get($url, [
        'query' => [
          'access_token' => $config['access_token'],
          'fields' => 'id,name,access_token', // Only request basic fields
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);

      if (!isset($data['id']) || $data['id'] !== $config['page_id']) {
        throw new \Exception('Invalid page access token or page ID.');
      }

      // Verify posting permission by attempting to get draft posts
      // This will fail if we don't have proper permissions
      $testUrl = sprintf(
        'https://graph.facebook.com/%s/%s/feed',
        $config['api_version'],
        $config['page_id']
      );

      $response = $this->httpClient->get($testUrl, [
        'query' => [
          'access_token' => $config['access_token'],
          'limit' => 1, // Only request one post to minimize data transfer
        ],
      ]);

      // If we get here, we have the necessary permissions
      return;

    } catch (RequestException $e) {
      $error = $this->parseErrorResponse($e);
      throw new \Exception('Failed to validate page access token: ' . $error);
    }
  }

  /**
   * Checks if a specific permission exists and is granted.
   */
  protected function hasPermission(array $permissions, string $permission_name): bool {
    foreach ($permissions as $permission) {
      if (isset($permission['permission'])
        && $permission['permission'] === $permission_name
        && isset($permission['status'])
        && $permission['status'] === 'granted') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Verifies if a token is a page access token.
   */
  protected function verifyPageAccessToken(string $token): bool {
    try {
      $url = 'https://graph.facebook.com/debug_token';
      $response = $this->httpClient->get($url, [
        'query' => [
          'input_token' => $token,
          'access_token' => $token,
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);
      return isset($data['data']['type']) && $data['data']['type'] === 'PAGE';
    } catch (\Exception $e) {
      return FALSE;
    }
  }
  /**
   * Posts a link with message to Facebook.
   */
  protected function postLink(array $data, array $config): string {
    $url = sprintf(
      'https://graph.facebook.com/%s/%s/feed',
      $config['api_version'],
      $config['page_id']
    );

    $response = $this->httpClient->post($url, [
      'form_params' => [
        'message' => $data['text'],
        'link' => $data['url'],
        'access_token' => $config['access_token'],
      ],
    ]);

    $result = json_decode($response->getBody(), TRUE);
    return json_encode([
      'post_id' => $result['id'],
      'type' => 'link',
      'text' => $data['text'],
    ]);
  }

  /**
   * Posts an image with caption to Facebook.
   */
  protected function postImage(array $data, array $config): string {
    // First, validate the image URL is accessible
    try {
      $this->httpClient->head($data['image_url']);
    } catch (\Exception $e) {
      throw new \Exception('Image URL is not accessible: ' . $data['image_url']);
    }

    $url = sprintf(
      'https://graph.facebook.com/%s/%s/photos',
      $config['api_version'],
      $config['page_id']
    );

    $response = $this->httpClient->post($url, [
      'form_params' => [
        'message' => $data['text'],
        'url' => $data['image_url'],
        'access_token' => $config['access_token'],
      ],
    ]);

    $result = json_decode($response->getBody(), TRUE);
    return json_encode([
      'post_id' => $result['id'],
      'type' => 'photo',
      'text' => $data['text'],
    ]);
  }

  /**
   * Finds Facebook content in the context.
   */
  protected function findFacebookContent(array $context): string {
    foreach ($context['results'] as $step) {
      if ($step['output_type'] === 'facebook_content') {
        if (!empty($step['data'])) {
          return $step['data'];
        }
        throw new \Exception("Facebook content is empty.");
      }
    }
    throw new \Exception("Facebook content not found in the context. Make sure the previous step has output_type 'facebook_content'.");
  }

  /**
   * Parses and validates the Facebook content.
   */
  protected function parseAndValidateContent(string $content): array {
    // Remove JSON code block markers if present
    $content = preg_replace('/^```json\s*|\s*```$/s', '', $content);
    $content = trim($content);

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON format: " . json_last_error_msg());
    }

    // Validate required text field
    if (empty($data['text'])) {
      throw new \Exception("JSON must contain a non-empty 'text' field");
    }

    // Validate URL or image URL
    if (!isset($data['url']) && !isset($data['image_url'])) {
      throw new \Exception("JSON must contain either 'url' or 'image_url' field");
    }

    // Validate URLs if present
    if (isset($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
      throw new \Exception("Link URL is not valid");
    }
    if (isset($data['image_url']) && !filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
      throw new \Exception("Image URL is not valid");
    }

    return $data;
  }

  /**
   * Parses error response from Facebook API.
   */
  protected function parseErrorResponse(RequestException $e): string {
    if (!$e->hasResponse()) {
      return $e->getMessage();
    }

    $response = json_decode($e->getResponse()->getBody(), TRUE);
    if (isset($response['error']['message'])) {
      return $response['error']['message'];
    }

    return $e->getMessage();
  }
}
