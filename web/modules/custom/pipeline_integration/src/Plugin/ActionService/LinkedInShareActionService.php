<?php
namespace Drupal\pipeline_integration\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "linkedin_share",
 *   label = @Translation("LinkedIn Share Action")
 * )
 */
class LinkedInShareActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {

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
   * Constructs a LinkedInShareActionService object.
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
      '#title' => $this->t('OAuth2 Access Token'),
      '#default_value' => $configuration['access_token'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your LinkedIn OAuth2 Access Token.'),
      '#rows' => 3,
    ];

    $form['author_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('LinkedIn Author URN'),
      '#default_value' => $configuration['author_id'] ?? '',
      '#description' => $this->t('The LinkedIn URN of the post author (urn:li:person:userID).'),
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
      'author_id' => $form_state->getValue('author_id'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      $linkedin_content = $this->findLinkedInContent($context);
      $data = $this->parseAndValidateContent($linkedin_content);

      $post_data = $this->buildSharePayload($data, $config['configuration']);

      $response = $this->httpClient->post('https://api.linkedin.com/v2/ugcPosts', [
        'headers' => [
          'Authorization' => 'Bearer ' . $config['configuration']['access_token'],
          'Content-Type' => 'application/json',
          'X-Restli-Protocol-Version' => '2.0.0',
        ],
        'json' => $post_data,
      ]);

      if ($response->getStatusCode() === 201) {
        $result = json_decode($response->getBody());
        return json_encode([
          'post_id' => $result->id,
          'text' => $data['text'],
          'type' => isset($data['media']) ? 'article' : 'text',
        ]);
      }

      throw new \Exception("LinkedIn API Error: Unexpected response");

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error posting to LinkedIn: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Finds LinkedIn content in the context.
   *
   * @param array $context
   *   The pipeline execution context.
   *
   * @return string
   *   The LinkedIn content data.
   *
   * @throws \Exception
   *   If LinkedIn content is not found in the context.
   */
  protected function findLinkedInContent(array $context): string {
    foreach ($context['results'] as $step) {
      if ($step['output_type'] === 'linkedin_content') {
        if (!empty($step['data'])) {
          return $step['data'];
        }
        throw new \Exception("LinkedIn content is empty.");
      }
    }
    throw new \Exception("LinkedIn content not found in the context. Make sure the previous step has output_type 'linkedin_content'.");
  }

  /**
   * Parses and validates the LinkedIn content.
   *
   * @param string $content
   *   The raw content to parse and validate.
   *
   * @return array
   *   The parsed and validated content.
   *
   * @throws \Exception
   *   If the content is invalid or missing required fields.
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

    // Validate media content if present
    if (isset($data['media'])) {
      if (!isset($data['media']['url'])) {
        throw new \Exception("Media content must include 'url' field");
      }
      if (!isset($data['media']['title'])) {
        throw new \Exception("Media content must include 'title' field");
      }
      if (!isset($data['media']['description'])) {
        throw new \Exception("Media content must include 'description' field");
      }

      // Validate URL format
      if (!filter_var($data['media']['url'], FILTER_VALIDATE_URL)) {
        throw new \Exception("Media URL is not valid");
      }

      // Validate thumbnail URL if present
      if (isset($data['media']['thumbnail']) && !filter_var($data['media']['thumbnail'], FILTER_VALIDATE_URL)) {
        throw new \Exception("Thumbnail URL is not valid");
      }
    }

    return $data;
  }

  /**
   * Builds the share payload for the LinkedIn API.
   *
   * @param array $data
   *   The validated content data.
   * @param array $config
   *   The service configuration.
   *
   * @return array
   *   The formatted payload for the LinkedIn API.
   */
  protected function buildSharePayload(array $data, array $config): array {
    $payload = [
      "author" => $config['author_id'],
      "lifecycleState" => "PUBLISHED",
      "specificContent" => [
        "com.linkedin.ugc.ShareContent" => [
          "shareCommentary" => [
            "text" => $data['text']
          ],
        ]
      ],
      "visibility" => [
        "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC"
      ]
    ];

    // Add media content if provided
    if (isset($data['media'])) {
      $payload['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'ARTICLE';
      $payload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
        [
          'status' => 'READY',
          'originalUrl' => $data['media']['url'],
          'title' => [
            'text' => $data['media']['title']
          ],
          'description' => [
            'text' => $data['media']['description']
          ]
        ]
      ];

      // Add thumbnail if provided
      if (isset($data['media']['thumbnail'])) {
        $payload['specificContent']['com.linkedin.ugc.ShareContent']['media'][0]['thumbnails'] = [
          ['url' => $data['media']['thumbnail']]
        ];
      }
    } else {
      $payload['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'NONE';
    }

    return $payload;
  }
}
