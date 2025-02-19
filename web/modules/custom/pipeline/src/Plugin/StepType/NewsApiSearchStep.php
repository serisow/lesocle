<?php
namespace Drupal\pipeline\Plugin\StepType;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;

/**
 * Provides a News API search step type.
 *
 * @StepType(
 *   id = "news_api_search",
 *   label = @Translation("News API Search Step"),
 *   description = @Translation("Searches news articles using NewsAPI.")
 * )
 */
class NewsApiSearchStep extends ConfigurableStepTypeBase implements StepTypeExecutableInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   *  Clean content from the news api.
   *
   * @var \Drupal\pipeline\Service\ArticleContentCleaner
   */
  protected $contentCleaner;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient = $container->get('http_client');
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return [
        'query' => '',
        'advanced_params' => [
          'language' => 'en',
          'sort_by' => 'publishedAt',
          'page_size' => 20,
          'date_range' => [
            'from' => null,
            'to' => null,
          ],
        ],
      ] + parent::additionalDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::additionalConfigurationForm($form, $form_state);

    $form['query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Query'),
      '#default_value' => $this->configuration['query'],
      '#description' => $this->t('Keywords or phrases to search for in the news. You can use placeholders like {step_key} to incorporate results from previous steps.'),
      '#required' => TRUE,
      '#maxlength' => 400,
    ];

    // Advanced parameters in a collapsible fieldset
    $form['advanced_params'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Parameters'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['advanced_params']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => [
        'ar' => $this->t('Arabic'),
        'de' => $this->t('German'),
        'en' => $this->t('English'),
        'es' => $this->t('Spanish'),
        'fr' => $this->t('French'),
        'it' => $this->t('Italian'),
        'nl' => $this->t('Dutch'),
        'no' => $this->t('Norwegian'),
        'pt' => $this->t('Portuguese'),
        'ru' => $this->t('Russian'),
        'zh' => $this->t('Chinese'),
      ],
      '#default_value' => $this->configuration['advanced_params']['language'],
    ];

    $form['advanced_params']['sort_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort By'),
      '#options' => [
        'relevancy' => $this->t('Relevancy'),
        'popularity' => $this->t('Popularity'),
        'publishedAt' => $this->t('Published Date'),
      ],
      '#default_value' => $this->configuration['advanced_params']['sort_by'],
    ];

    $form['advanced_params']['page_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Results Per Page'),
      '#min' => 1,
      '#max' => 100,
      '#default_value' => $this->configuration['advanced_params']['page_size'],
    ];

    // Add date range fieldset
    $form['advanced_params']['date_range'] = [
      '#type' => 'details',
      '#title' => $this->t('Date Range'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['advanced_params']['date_range']['from'] = [
      '#type' => 'textfield',  // Changed from datetime to textfield
      '#title' => $this->t('From Date'),
      '#default_value' => $this->configuration['advanced_params']['date_range']['from'] ?? '',
      '#description' => $this->t('Articles published on or after this date. Format: YYYY-MM-DD'),
      '#size' => 10,
      '#maxlength' => 10,
      '#placeholder' => 'YYYY-MM-DD',
    ];

    $form['advanced_params']['date_range']['to'] = [
      '#type' => 'textfield',  // Changed from datetime to textfield
      '#title' => $this->t('To Date'),
      '#default_value' => $this->configuration['advanced_params']['date_range']['to'] ?? '',
      '#description' => $this->t('Articles published on or before this date. Format: YYYY-MM-DD'),
      '#size' => 10,
      '#maxlength' => 10,
      '#placeholder' => 'YYYY-MM-DD',
    ];
    // Add validation
    $form['#element_validate'][] = [$this, 'validateDateRange'];

    return $form;
  }

  /**
   * Validates the date range input.
   */
  public function validateDateRange(array &$form, FormStateInterface $form_state) {
    $from = $form_state->getValue(['data', 'advanced_params', 'date_range', 'from']);
    $to = $form_state->getValue(['data', 'advanced_params', 'date_range', 'to']);

    // Validate date format if not empty
    foreach (['from', 'to'] as $field) {
      $value = $form_state->getValue(['data', 'advanced_params', 'date_range', $field]);
      if (!empty($value)) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
          $form_state->setError($form['data']['advanced_params']['date_range'][$field],
            $this->t('Date must be in YYYY-MM-DD format.'));
          continue;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
          $form_state->setError($form['data']['advanced_params']['date_range'][$field],
            $this->t('Invalid date.'));
        }
      }
    }

    // Validate date range if both dates are set
    if (!empty($from) && !empty($to)) {
      $from_date = strtotime($from);
      $to_date = strtotime($to);
      if ($from_date > $to_date) {
        $form_state->setError($form['data']['advanced_params']['date_range']['from'],
          $this->t('From date cannot be later than To date.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    parent::submitConfigurationForm($form, $form_state);

    $from_date = $form_state->getValue(['data', 'advanced_params', 'date_range', 'from']);
    $to_date = $form_state->getValue(['data', 'advanced_params', 'date_range', 'to']);

    $this->configuration['query'] = $form_state->getValue(['data', 'query']);
    $this->configuration['advanced_params'] = [
      'language' => $form_state->getValue(['data', 'advanced_params', 'language']),
      'sort_by' => $form_state->getValue(['data', 'advanced_params', 'sort_by']),
      'page_size' => (int) $form_state->getValue(['data', 'advanced_params', 'page_size']),
      'date_range' => [
        'from' => $form_state->getValue(['data', 'advanced_params', 'date_range', 'from']),
        'to' => $form_state->getValue(['data', 'advanced_params', 'date_range', 'to']),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string
  {
    try {
      $config = $this->getConfiguration()['data'];

      $news_api_config = $this->configFactory->get('pipeline.news_api_settings');
      $api_key = $news_api_config->get('news_api_key');

      if (!$api_key) {
        throw new \Exception("News API key is not configured.");
      }

      // Process dynamic query from context
      $query = $config['query'];
      foreach ($context['results'] as $step_key => $result) {
        $placeholder = '{' . $step_key . '}';
        if (strpos($query, $placeholder) !== false) {
          $query = str_replace($placeholder, $result['data'], $query);
        }
      }

      $params = [
        'q' => $query,
        'language' => $config['advanced_params']['language'],
        'sortBy' => $config['advanced_params']['sort_by'],
        'pageSize' => $config['advanced_params']['page_size'],
        'apiKey' => $api_key,
      ];

      // Add date parameters if set
      if (!empty($config['advanced_params']['date_range']['from'])) {
        $params['from'] = $config['advanced_params']['date_range']['from'];
      }
      if (!empty($config['advanced_params']['date_range']['to'])) {
        $params['to'] = $config['advanced_params']['date_range']['to'];
      }

      $url = 'https://newsapi.org/v2/everything?' . http_build_query($params);

      $response = $this->httpClient->get($url);
      $data = json_decode($response->getBody(), TRUE);

      if ($response->getStatusCode() !== 200) {
        throw new \Exception('News API error: ' . ($data['message'] ?? 'Unknown error'));
      }

      $formatted_results = [
        'query' => $query,
        'total_results' => $data['totalResults'],
        'articles' => [],
        'metadata' => [
          'timestamp' => time(),
          'language' => $params['language'],
          'sort_by' => $params['sortBy'],
        ],
      ];

      foreach ($data['articles'] as $article) {
        $expanded_content = $this->fetchExpandedContent($article['url']);

        $formatted_results['articles'][] = [
          'title' => $article['title'],
          'description' => $article['description'],
          'url' => $article['url'],
          'published_at' => $article['publishedAt'],
          'source' => $article['source']['name'],
          'author' => $article['author'],
          'image_url' => $article['urlToImage'] ?? null,
          'expanded_content' => $expanded_content,
        ];
      }

      $result = json_encode($formatted_results);
      $this->configuration['response'] = $result;
      $context['results'][$this->getStepOutputKey()] = [
        'output_type' => $this->configuration['output_type'],
        'service' => 'news_api_search',
        'data' => $result,
      ];

      return $result;

    } catch (\Exception $e) {
      throw new \Exception("Error fetching news results: " . $e->getMessage());
    }
  }

  function fetchExpandedContent(string $url): string {
    try {
      // Skip problematic URLs
      if (str_contains($url, 'consent.yahoo.com')) {
        return "Content unavailable - requires consent";
      }

      $response = $this->httpClient->get($url, [
        'headers' => [
          'User-Agent' => 'Mozilla/5.0 (compatible; Drupal/10.0; +http://example.com)',
        ],
        'timeout' => 10,
      ]);

      $html = $response->getBody()->getContents();
      // Configure DOMDocument with error suppression
      $dom = new \DOMDocument();
      libxml_use_internal_errors(true);

      // Load HTML with proper encoding handling
      $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

      // First pass: Remove obviously unwanted elements by tag name
      $tagsToRemove = [
        'script', 'style', 'iframe', 'noscript', 'nav',
        'footer', 'header', 'form', 'button', 'input',
        'select', 'textarea', 'svg', 'img', 'meta',
        'link', 'object', 'embed', 'applet', 'frame',
        'frameset', 'map', 'area', 'audio', 'video',
        'source', 'track', 'canvas', 'datalist', 'keygen',
        'output', 'progress', 'time'
      ];

      foreach ($tagsToRemove as $tag) {
        $elements = $dom->getElementsByTagName($tag);
        while ($element = $elements->item(0)) {
          $element->parentNode->removeChild($element);
        }
      }

      // Second pass: Remove elements by class/id patterns using XPath
      $xpath = new \DOMXPath($dom);
      $unwantedPatterns = [
        '//comment()',  // Remove all comments
        '//*[contains(@class, "ad")]',
        '//*[contains(@class, "banner")]',
        '//*[contains(@class, "sidebar")]',
        '//*[contains(@class, "popup")]',
        '//*[contains(@class, "cookie")]',
        '//*[contains(@class, "modal")]',
        '//*[contains(@class, "newsletter")]',
        '//*[contains(@class, "yns-")]',          // Target Yahoo notification classes
        '//*[contains(@class, "ybar-")]',         // Target Yahoo bar classes
        '//*[contains(@id, "ad")]',
        '//*[contains(@id, "banner")]',
        '//*[contains(@id, "sidebar")]',
        '//*[contains(@id, "popup")]',
        '//*[contains(@id, "cookie")]',
        '//*[contains(@id, "modal")]',
        '//*[contains(@id, "newsletter")]',
        '//*[contains(@id, "notification")]',     // Target notification IDs
        '//*[@role="navigation"]',
        '//*[@role="banner"]',
        '//*[@role="complementary"]',
        '//*[@role="dialog"]',                    // Remove dialogs/alerts
        '//*[@role="search"]',
      ];

      foreach ($unwantedPatterns as $pattern) {
        $elements = $xpath->query($pattern);
        foreach ($elements as $element) {
          $element->parentNode->removeChild($element);
        }
      }

      // Third pass: Content heuristics
      $allElements = $xpath->query('//*');
      $MIN_TEXT_LENGTH = 120;  // Minimum characters to consider as content
      $MAX_LINK_DENSITY = 0.25; // Maximum links per character ratio

      // Process in reverse order to safely remove parent elements
      for ($i = $allElements->length - 1; $i >= 0; $i--) {
        $element = $allElements->item($i);
        if (!$element->parentNode) continue;

        $text = trim(preg_replace('/\s+/', ' ', $element->textContent));
        $textLength = mb_strlen($text);
        $links = $element->getElementsByTagName('a');
        $linkCount = $links->length;
        $linkDensity = ($textLength > 0) ? ($linkCount / $textLength) : 0;

        // Remove elements with:
        // - Too short text
        // - Too many links
        // - Empty content
        if ($textLength < $MIN_TEXT_LENGTH ||
          $linkDensity > $MAX_LINK_DENSITY ||
          empty($text)) {
          $element->parentNode->removeChild($element);
        }
      }

      // Final content extraction and cleanup
      $body = $dom->getElementsByTagName('body')->item(0);
      $textContent = $body ? $body->textContent : '';

      // Advanced cleanup
      $textContent = html_entity_decode($textContent);
      $textContent = preg_replace('/\s+/', ' ', $textContent); // Collapse whitespace
      $textContent = preg_replace('/\[\d+\]/', '', $textContent); // Remove citation numbers [1]
      $textContent = preg_replace('/\bhttps?:\/\/\S+/i', '', $textContent); // Remove URLs

      if (strlen($textContent) > 800) {
        $textContent = substr($textContent, 0, 2000);
        $textContent = substr($textContent, 0, strrpos($textContent, '.') + 1);
      }
      return trim($textContent);

    } catch (\Exception $e) {
      // Log error but don't expose details in returned content
      $this->logger->error('Content extraction failed for @url: @error', [
        '@url' => $url,
        '@error' => $e->getMessage(),
      ]);
      return "Error fetching content";
    }
  }
}
