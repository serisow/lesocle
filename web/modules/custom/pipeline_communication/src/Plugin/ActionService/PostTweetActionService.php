<?php
namespace Drupal\pipeline_communication\Plugin\ActionService;

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
 *   id = "post_tweet",
 *   label = @Translation("Post Tweet Action")
 * )
 */
class PostTweetActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {

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
   * Constructs a PostTweetActionService object.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
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
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration) {
    $form['consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer Key (API Key)'),
      '#default_value' => $configuration['consumer_key'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twitter API Consumer Key.'),
    ];

    $form['consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer Secret (API Secret)'),
      '#default_value' => $configuration['consumer_secret'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twitter API Consumer Secret.'),
    ];

    $form['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#default_value' => $configuration['access_token'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twitter Access Token.'),
    ];

    $form['access_token_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token Secret'),
      '#default_value' => $configuration['access_token_secret'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twitter Access Token Secret.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    return [
      'consumer_key' => $form_state->getValue('consumer_key'),
      'consumer_secret' => $form_state->getValue('consumer_secret'),
      'access_token' => $form_state->getValue('access_token'),
      'access_token_secret' => $form_state->getValue('access_token_secret'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      // Find the tweet content in the context
      $tweet_content = null;
      foreach ($context['results'] as $step) {
        if ($step['output_type'] === 'tweet_content') {
          $tweet_content = $step['data'];
          break;
        }
      }

      if (!$tweet_content) {
        throw new \Exception("Tweet content not found in the context.");
      }

      // Remove ```json prefix and ``` suffix if present
      $content = preg_replace('/^```json\s*|\s*```$/s', '', $tweet_content);

      // Trim any whitespace
      $content = trim($content);

      // Decode the JSON content
      $data = json_decode($content, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Invalid JSON format: " . json_last_error_msg());
      }

      if (!isset($data['text'])) {
        throw new \Exception("JSON must contain 'text' field");
      }

      // Create TwitterOAuth connection
      $connection = new TwitterOAuth(
        $config['configuration']['consumer_key'],
        $config['configuration']['consumer_secret'],
        $config['configuration']['access_token'],
        $config['configuration']['access_token_secret']
      );

      // Set API version to 2
      $connection->setApiVersion('2');

      // Post the tweet
      $tweet = $connection->post("tweets", [
        "text" => $data['text']
      ], [
        'headers' => [
          'Content-Type' => 'application/json',
        ]
      ]);

      // Check the response
      if ($connection->getLastHttpCode() == 201) {
        return json_encode([
          'tweet_id' => $tweet->data->id,
          'text' => $data['text'],
        ]);
      }

      // Handle error case
      $error_message = isset($tweet->errors[0]->message)
        ? $tweet->errors[0]->message
        : 'Unknown Twitter API error';

      throw new \Exception("Twitter API Error: " . $error_message);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error posting tweet: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }
}
