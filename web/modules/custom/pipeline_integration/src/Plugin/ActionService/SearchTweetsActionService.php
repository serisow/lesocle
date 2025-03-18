<?php
namespace Drupal\pipeline_integration\Plugin\ActionService;

use Abraham\TwitterOAuth\TwitterOAuth;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "search_tweets",
 *   label = @Translation("Search Tweets Action"),
 * )
 */
class SearchTweetsActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {

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
   * Constructs a SearchTweetsActionService object.
   */
  public function __construct(
    array  $configuration,
           $plugin_id,
           $plugin_definition,
    EntityTypeManagerInterface    $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration)
  {
    // API credentials section
    $form['api_credentials'] = [
      '#type' => 'details',
      '#title' => $this->t('API Credentials'),
      '#open' => TRUE,
      '#required' => TRUE,
    ];

    $form['api_credentials']['consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer Key (API Key)'),
      '#default_value' => $configuration['consumer_key'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twitter API Consumer Key.'),
    ];

    $form['api_credentials']['consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer Secret'),
      '#default_value' => $configuration['consumer_secret'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twitter API Consumer Secret.'),
    ];

    $form['api_credentials']['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#default_value' => $configuration['access_token'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twitter Access Token.'),
    ];

    $form['api_credentials']['access_token_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token Secret'),
      '#default_value' => $configuration['access_token_secret'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twitter Access Token Secret.'),
    ];

    // Search configuration section
    $form['search_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Search Configuration'),
      '#open' => TRUE,
    ];

    $form['search_config']['search_query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Search Query'),
      '#description' => $this->t('Enter your Twitter search query. You can use Twitter search operators (e.g., "from:user OR #hashtag -filter:retweets lang:en"). You can also use placeholders like {step_key} to incorporate results from previous steps. The query can include multiple lines for better readability.'),
      '#required' => TRUE,
      '#default_value' => $configuration['search_query'] ?? '',
      '#rows' => 5,
    ];

    $form['search_config']['max_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of Tweets'),
      '#description' => $this->t('Number of tweets to retrieve (10-100)'),
      '#default_value' => $configuration['max_results'] ?? 10,
      '#min' => 10,
      '#max' => 100,
      '#step' => 10,
      '#required' => TRUE,
    ];

    // Advanced options section
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['advanced']['include_metrics'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include Metrics'),
      '#default_value' => $configuration['include_metrics'] ?? TRUE,
      '#description' => $this->t('Include engagement metrics (likes, retweets, etc.)'),
    ];

    $form['advanced']['result_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Result Type'),
      '#options' => [
        'recent' => $this->t('Recent'),
        'popular' => $this->t('Popular'),
        'mixed' => $this->t('Mixed'),
      ],
      '#default_value' => $configuration['result_type'] ?? 'recent',
      '#description' => $this->t('Type of tweets to return.'),
    ];

    $form['advanced']['include_entities'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include Entities'),
      '#default_value' => $configuration['include_entities'] ?? FALSE,
      '#description' => $this->t('Include additional metadata like hashtags, mentions, and URLs.'),
    ];

    $form['advanced']['rate_limiting'] = [
      '#type' => 'details',
      '#title' => $this->t('Rate Limiting'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['advanced']['rate_limiting']['max_retries'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Retries'),
      '#description' => $this->t('Maximum number of retry attempts when rate limited.'),
      '#default_value' => $configuration['max_retries'] ?? 3,
      '#min' => 1,
      '#max' => 5,
    ];

    $form['advanced']['rate_limiting']['max_wait_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Wait Time'),
      '#description' => $this->t('Maximum time to wait (in seconds) between retries.'),
      '#default_value' => $configuration['max_wait_time'] ?? 60,
      '#min' => 10,
      '#max' => 300,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    return [
      'consumer_key' => $form_state->getValue('consumer_key'),
      'consumer_secret' => $form_state->getValue('consumer_secret'),
      'access_token' => $form_state->getValue('access_token'),
      'access_token_secret' => $form_state->getValue('access_token_secret'),
      'search_query' => $form_state->getValue('search_query'),
      'max_results' => $form_state->getValue(['max_results']),
      'include_metrics' => $form_state->getValue('include_metrics'),
      'result_type' => $form_state->getValue('result_type'),
      'include_entities' => $form_state->getValue('include_entities'),
      'max_retries' => $form_state->getValue('max_retries'),
      'max_wait_time' => $form_state->getValue('max_wait_time'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      // Process dynamic query from context
      $search_query = $config['configuration']['search_query'];
      foreach ($context['results'] as $step_key => $result) {
        $placeholder = '{' . $step_key . '}';
        if (strpos($search_query, $placeholder) !== false) {
          $search_query = str_replace($placeholder, $result['data'], $search_query);
        }
      }

      // Initialize Twitter connection
      $connection = new TwitterOAuth(
        $config['configuration']['consumer_key'],
        $config['configuration']['consumer_secret'],
        $config['configuration']['access_token'],
        $config['configuration']['access_token_secret']
      );

      $connection->setApiVersion('2');

      // Build search parameters
      $params = [
        'query' => $search_query,
        'max_results' => $config['configuration']['max_results'],
        'tweet.fields' => 'created_at,author_id'
      ];

      if ($config['configuration']['include_metrics']) {
        $params['tweet.fields'] .= ',public_metrics';
      }

      if ($config['configuration']['include_entities']) {
        $params['tweet.fields'] .= ',entities';
      }

      // Add retry logic with exponential backoff
      $maxRetries = 3;
      $attempt = 0;
      $response = null;

      while ($attempt < $maxRetries) {
        $response = $connection->get('tweets/search/recent', $params);
        $httpCode = $connection->getLastHttpCode();

        if ($httpCode === 200) {
          break;
        }

        // Check for rate limit headers
        $headers = $connection->getLastXHeaders();

        if ($httpCode === 429) {
          $resetTime = $headers['x-rate-limit-reset'] ?? null;
          if ($resetTime) {
            $waitTime = $resetTime - time();
            if ($waitTime > 0) {
              $this->loggerFactory->get('pipeline')->warning(
                'Rate limit hit, waiting @seconds seconds before retry. Attempt @attempt of @max',
                [
                  '@seconds' => $waitTime,
                  '@attempt' => $attempt + 1,
                  '@max' => $maxRetries
                ]
              );
              sleep(min($waitTime, 60)); // Wait up to 60 seconds maximum
            }
          } else {
            // If no reset time available, use exponential backoff
            $backoffSeconds = pow(2, $attempt);
            $this->loggerFactory->get('pipeline')->warning(
              'Rate limit hit with no reset time, backing off for @seconds seconds. Attempt @attempt of @max',
              [
                '@seconds' => $backoffSeconds,
                '@attempt' => $attempt + 1,
                '@max' => $maxRetries
              ]
            );
            sleep($backoffSeconds);
          }
          $attempt++;
          continue;
        }

        // For other errors, break immediately
        break;
      }

      // Final validation of response
      if ($attempt === $maxRetries) {
        throw new \Exception('Maximum retry attempts reached for Twitter search');
      }

      $this->validateResponse($response, $connection->getLastHttpCode());

      // Process and format results
      $tweets = [];
      foreach ($response->data as $tweet) {
        $tweet_data = [
          'id' => $tweet->id,
          'text' => $tweet->text,
          'created_at' => $tweet->created_at,
          'author_id' => $tweet->author_id,
        ];

        if ($config['configuration']['include_metrics'] && isset($tweet->public_metrics)) {
          $tweet_data['metrics'] = [
            'retweets' => $tweet->public_metrics->retweet_count ?? 0,
            'replies' => $tweet->public_metrics->reply_count ?? 0,
            'likes' => $tweet->public_metrics->like_count ?? 0,
            'quotes' => $tweet->public_metrics->quote_count ?? 0,
          ];
        }

        if ($config['configuration']['include_entities'] && isset($tweet->entities)) {
          $tweet_data['entities'] = $tweet->entities;
        }

        $tweets[] = $tweet_data;
      }

      return json_encode([
        'status' => 'success',
        'service' => 'twitter_search',
        'data' => [
          'tweets' => $tweets,
          'metadata' => [
            'query' => $search_query,
            'max_results' => $config['configuration']['max_results'],
            'found_count' => count($tweets),
            'timestamp' => \Drupal::time()->getCurrentTime(),
            'result_type' => $config['configuration']['result_type'],
            'rate_limit_remaining' => $connection->getLastXHeaders()['x-rate-limit-remaining'] ?? null,
          ],
        ],
      ]);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Tweet search failed: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Validates the Twitter API response.
   *
   * @param mixed $response
   *   The API response.
   * @param int $http_code
   *   The HTTP response code.
   *
   * @throws \Exception
   *   When the response is invalid or contains an error.
   */
  private function validateResponse($response, $http_code)
  {
    if ($http_code !== 200) {
      $error = is_object($response) && isset($response->errors[0]->message)
        ? $response->errors[0]->message
        : 'Unknown API error';

      throw new \Exception(sprintf(
        'Twitter search failed (HTTP %d): %s',
        $http_code,
        $error
      ));
    }

    if (!isset($response->data)) {
      throw new \Exception('Invalid response format from Twitter API');
    }
  }
}
