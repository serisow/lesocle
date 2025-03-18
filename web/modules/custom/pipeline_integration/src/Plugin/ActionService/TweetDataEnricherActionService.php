<?php
namespace Drupal\pipeline_integration\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "tweet_data_enricher",
 *   label = @Translation("Tweet Data Enricher Action")
 * )
 */
class TweetDataEnricherActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {
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
   * Constructs a new TweetDataEnricherActionService.
   */
  public function __construct(
    array                         $configuration,
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
    $form['include_tweet_urls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include Tweet URLs'),
      '#default_value' => $configuration['include_tweet_urls'] ?? TRUE,
      '#description' => $this->t('Generate direct links to tweets.'),
    ];

    $form['include_user_profiles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include User Profiles'),
      '#default_value' => $configuration['include_user_profiles'] ?? TRUE,
      '#description' => $this->t('Include links to user profiles.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    return [
      'include_tweet_urls' => $form_state->getValue('include_tweet_urls'),
      'include_user_profiles' => $form_state->getValue('include_user_profiles'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string
  {
    try {
      // Get tweet search results
      $tweet_search_data = null;
      $crisis_analysis = null;

      // Find the required data in context based on output_type
      foreach ($context['results'] as $result) {
        if (isset($result['output_type'])) {
          switch ($result['output_type']) {
            case 'twitter_search_results':
              $tweet_search_data = json_decode($result['data'], TRUE);
              break;

            case 'crisis_analysis_results':
              $crisis_analysis = json_decode($result['data'], TRUE);
              break;
          }
        }
      }

      if (!$tweet_search_data) {
        throw new \Exception('Tweet search results not found in context.');
      }
      if (!$crisis_analysis) {
        throw new \Exception('Crisis analysis results not found in context.');
      }

      // Create a lookup of high priority tweets
      $high_priority_tweets = [];
      foreach ($crisis_analysis['high_priority_tweets'] as $priority_tweet) {
        $high_priority_tweets[$priority_tweet['tweet_id']] = $priority_tweet['reason'];
      }

      // Enrich tweet data
      $enriched_tweets = [];
      foreach ($tweet_search_data['data']['tweets'] as $tweet) {
        $tweet_url = $config['configuration']['include_tweet_urls']
          ? "https://twitter.com/i/web/status/{$tweet['id']}"
          : null;

        $user_profile = $config['configuration']['include_user_profiles'] && isset($tweet['author_id'])
          ? "https://twitter.com/i/user/{$tweet['author_id']}"
          : null;

        $enriched_tweet = [
          'id' => $tweet['id'],
          'text' => $tweet['text'],
          'created_at' => $tweet['created_at'],
          'author' => [
            'id' => $tweet['author_id'],
            'profile_url' => $user_profile,
          ],
          'metrics' => $tweet['metrics'] ?? [],
          'tweet_url' => $tweet_url,
          'is_high_priority' => isset($high_priority_tweets[$tweet['id']]),
          'priority_reason' => $high_priority_tweets[$tweet['id']] ?? null,
        ];

        if (isset($tweet['entities'])) {
          $enriched_tweet['entities'] = $tweet['entities'];
        }

        $enriched_tweets[] = $enriched_tweet;
      }

      // Create the enriched data structure
      $enriched_data = [
        'crisis_metrics' => [
          'severity_score' => $crisis_analysis['severity_score'],
          'sentiment_analysis' => $crisis_analysis['sentiment_analysis'],
          'viral_potential' => $crisis_analysis['viral_potential'],
        ],
        'tweets' => [
          'all_tweets' => $enriched_tweets,
          'high_priority_tweets' => array_filter($enriched_tweets, function ($tweet) {
            return $tweet['is_high_priority'];
          }),
        ],
        'metadata' => [
          'total_tweets' => count($enriched_tweets),
          'high_priority_count' => count($crisis_analysis['high_priority_tweets']),
          'search_query' => $tweet_search_data['data']['metadata']['query'],
          'timestamp' => time(),
        ],
        'recommended_actions' => $crisis_analysis['recommended_actions'],
      ];

      return json_encode($enriched_data);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Tweet data enrichment failed: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

}
