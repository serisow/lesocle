<?php
namespace Drupal\pipeline\Plugin\StepType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @StepType(
 *   id = "social_media_step",
 *   label = @Translation("Social Media Step"),
 *   description = @Translation("Prepares article content for social media distribution.")
 * )
 */
class SocialMediaStep extends ConfigurableStepTypeBase implements StepTypeExecutableInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return [
      'article' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $default_value = NULL;
    if (!empty($this->configuration['article'])) {
      $string = trim($this->configuration['article'], '"');
      if (preg_match('/\((\d+)\)$/', $string, $matches)) {
        $nid = (int) $matches[1];
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if ($node) {
          $default_value = $node;
        }
      }
    }

    $form['article'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Article'),
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['article'],
        'status' => [1],
      ],
      '#required' => TRUE,
      '#default_value' => $default_value,
      '#description' => $this->t('Select the article to prepare for social media sharing.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['article'] = $form_state->getValue(['data', 'article']);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string {
    try {
      // Step 1: Get and trim the string from the configuration
      $string = trim($this->configuration['article'], '"');

      // Step 2: Extract the node ID using a regular expression
      if (preg_match('/\((\d+)\)$/', $string, $matches)) {
        // Step 3: Cast the captured ID to an integer
        $nid = (int) $matches[1];

        // Step 4: Load the node with the extracted ID
        $node = $this->entityTypeManager->getStorage('node')->load($nid);

        // Step 5: Check if the node was loaded successfully
        if (!$node) {
          throw new \Exception('Article not found');
        }
      } else {
        throw new \Exception('Invalid article format');
      }
      $article_url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
      $image_url = null;

      // Get featured image if available
      if ($node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
        $image = $node->get('field_media_image')->entity;
        if ($image) {
          $image_url = $image->createFileUrl();
        }
      }

      // Get body and summary
      $body = $node->get('body')->value;
      $summary = $node->hasField('field_summary') ?
        $node->get('field_summary')->value :
        $this->generateSummary($body);

      // Prepare metadata
      $metadata = [
        'article_nid' => $node->id(),
        'title' => $node->getTitle(),
        'url' => $article_url,
        'image_url' => $image_url,
        'summary' => $summary,
        'body' => $body,
      ];

      // Prepare Twitter content
      $tweet_text = $this->generateTweet($metadata);

      $twitter_content = [
        'text' => $tweet_text,
      ];

      // Always include article link and image for Twitter
      if ($image_url) {
        $twitter_content['media'] = [
          'url' => $article_url,
          'image_url' => $image_url
        ];
      }

      // Prepare LinkedIn content
      $linkedin_text = $this->generateLinkedInPost($metadata);


      // LinkedIn requires non-empty text field
      if (empty(trim($linkedin_text))) {
        throw new \Exception('LinkedIn post text cannot be empty');
      }

      $linkedin_content = [
        'text' => $linkedin_text,
        'media' => [
          'url' => $article_url,
          'title' => $metadata['title'],
          'description' => $this->generateSummary($metadata['summary'], 200),
        ]
      ];

      if ($image_url) {
        $linkedin_content['media']['thumbnail'] = $image_url;
      }

      // Store platform-specific content in context with output types matching action service expectations
      $context['results'][$this->getStepOutputKey() . '_twitter'] = [
        'output_type' => 'tweet_content',
        'data' => json_encode($twitter_content)
      ];

      $context['results'][$this->getStepOutputKey() . '_linkedin'] = [
        'output_type' => 'linkedin_content',
        'data' => json_encode($linkedin_content)
      ];

      // Store article metadata for potential use by other steps
      $context['results'][$this->getStepOutputKey() . '_metadata'] = [
        'output_type' => 'article_data',
        'data' => json_encode($metadata)
      ];

      // Return comprehensive data
      return json_encode([
        'article_id' => $node->id(),
        'platforms' => [
          'twitter' => $twitter_content,
          'linkedin' => $linkedin_content,
        ],
        'metadata' => $metadata,
      ]);

    } catch (\Exception $e) {
      throw new \Exception('Error preparing social media content: ' . $e->getMessage());
    }
  }

  /**
   * Generates a tweet from article metadata.
   */
  protected function generateTweet(array $metadata): string {
    // Generate engaging tweet text using title and summary
    $summary = $this->generateSummary($metadata['summary'], 180); // Shorter for tweet
    $tweet = $metadata['title'] . "\n\n" . $summary;

    // Truncate to leave room for URL if needed
    return $this->ensureTweetLength($tweet, $metadata['url']);
  }

  /**
   * Ensures tweet with URL fits character limit.
   */
  protected function ensureTweetLength(string $tweet, string $url): string {
    $url_length = 23; // Twitter treats all URLs as 23 characters
    $max_length = 280 - $url_length - 1; // -1 for space before URL

    if (mb_strlen($tweet) > $max_length) {
      $tweet = mb_substr($tweet, 0, $max_length - 1) . '…';
    }

    return $tweet . ' ' . $url;
  }

  /**
   * Generates a LinkedIn post from article metadata.
   */
  protected function generateLinkedInPost(array $metadata): string {
    // Generate a more detailed post for LinkedIn
    $summary = $this->generateSummary($metadata['summary'], 500); // Longer for LinkedIn
    $post = [
      $metadata['title'],
      '',  // Empty line after title
      $summary,
      '',  // Empty line before call to action
      '🔍 Read the full article for more details.',
    ];

    return implode("\n", $post);
  }

  /**
   * Generates a summary from body text.
   */
  protected function generateSummary(string $body, int $length = 250): string {
    $text = strip_tags($body);
    if (mb_strlen($text) <= $length) {
      return $text;
    }

    $summary = mb_substr($text, 0, $length);
    return mb_substr($summary, 0, mb_strrpos($summary, ' ')) . '...';
  }
}
