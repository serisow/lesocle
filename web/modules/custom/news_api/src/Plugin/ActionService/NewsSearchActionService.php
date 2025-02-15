<?php
namespace Drupal\news_api\Plugin\ActionService;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "news_api_search",
 *   label = @Translation("News API Search Action")
 * )
 */
class NewsSearchActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface
{

  protected $httpClient;
  protected $loggerFactory;

  public function __construct(
    array                         $configuration,
                                  $plugin_id,
                                  $plugin_definition,
    ClientInterface               $http_client,
    LoggerChannelFactoryInterface $logger_factory
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('logger.factory')
    );
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration)
  {
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $configuration['api_key'] ?? '',
      '#required' => TRUE,
    ];

    $form['query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Query'),
      '#default_value' => $configuration['query'] ?? '',
      '#description' => $this->t('Keywords or phrases to search for in the news.'),
      '#required' => TRUE,
    ];

    $form['language'] = [
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
      '#default_value' => $configuration['language'] ?? 'en',
    ];

    $form['sort_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort By'),
      '#options' => [
        'relevancy' => $this->t('Relevancy'),
        'popularity' => $this->t('Popularity'),
        'publishedAt' => $this->t('Published Date'),
      ],
      '#default_value' => $configuration['sort_by'] ?? 'publishedAt',
    ];

    $form['page_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Results Per Page'),
      '#min' => 1,
      '#max' => 100,
      '#default_value' => $configuration['page_size'] ?? 20,
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    return [
      'api_key' => $form_state->getValue('api_key'),
      'query' => $form_state->getValue('query'),
      'language' => $form_state->getValue('language'),
      'sort_by' => $form_state->getValue('sort_by'),
      'page_size' => $form_state->getValue('page_size'),
    ];
  }

  public function executeAction(array $config, array &$context): string
  {
    try {
      // Process dynamic query from context if needed
      $query = $config['configuration']['query'];
      foreach ($context['results'] as $step_key => $result) {
        $placeholder = '{' . $step_key . '}';
        if (strpos($query, $placeholder) !== false) {
          $query = str_replace($placeholder, $result['data'], $query);
        }
      }

      $params = [
        'q' => $query,
        'language' => $config['configuration']['language'],
        'sortBy' => $config['configuration']['sort_by'],
        'pageSize' => $config['configuration']['page_size'],
        'apiKey' => $config['configuration']['api_key'],
      ];

      // Add date params from context if available
      if (isset($context['date_from'])) {
        $params['from'] = $context['date_from'];
      }
      if (isset($context['date_to'])) {
        $params['to'] = $context['date_to'];
      }

      $url = 'https://newsapi.org/v2/everything?' . http_build_query($params);

      $response = $this->httpClient->get($url);
      $data = json_decode($response->getBody(), TRUE);

      if ($response->getStatusCode() !== 200) {
        throw new \Exception('News API error: ' . ($data['message'] ?? 'Unknown error'));
      }

      // Process and format results with expanded content
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

      // Process each article and fetch expanded content
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

      return json_encode($formatted_results);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('News search failed: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Fetches and extracts expanded content from article URL.
   *
   * @param string $url
   *   The URL of the article.
   *
   * @return string
   *   The extracted content or error message.
   */
  protected function fetchExpandedContent($url) {
    try {
      // Skip known problematic URLs
      //@TODO ssow: as we play with the api, we will keep adding test for particular website
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
      $dom = new \DOMDocument();
      @$dom->loadHTML($html, LIBXML_NOERROR);
      $xpath = new \DOMXPath($dom);

      // First try article-specific selectors
      //@TODO ssow: as we play with the api, we will keep adding selector for particular website
      $contentSelectors = [
        '//article[contains(@class, "article-content")]',
        '//div[contains(@class, "article-body")]',
        '//div[contains(@class, "story-content")]',
        // More specific selectors first
        '//div[contains(@class, "post-content")]',
        // Then fall back to general content areas
        '//main/article',
        '//div[@role="main"]',
      ];

      // Try each selector until we find content
      foreach ($contentSelectors as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes->length > 0) {
          $content = $this->cleanContent($nodes->item(0)->textContent);
          if (strlen($content) > 100) { // Validate content is substantial
            return $content;
          }
        }
      }

      // Fallback to basic content extraction
      $content_nodes = $xpath->query('//article | //div[@class="content"]');
      if ($content_nodes->length > 0) {
        return $this->cleanContent($content_nodes->item(0)->textContent);
      }

      return "No article content found.";
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Content expansion failed for @url: @error', [
        '@url' => $url,
        '@error' => $e->getMessage(),
      ]);
      return "Error fetching content: " . $e->getMessage();
    }
  }

  /**
   * Cleans and formats extracted content.
   */
  protected function cleanContent($content) {
    // Remove extra whitespace
    $content = preg_replace('/\s+/', ' ', $content);

    // Remove common cruft
    $content = preg_replace('/^(Share|Comments|Published|By|Author).+?\n/im', '', $content);

    // Trim length while respecting word boundaries
    if (strlen($content) > 2000) {
      $content = substr($content, 0, 2000);
      $content = substr($content, 0, strrpos($content, '.') + 1);
    }

    return trim($content);
  }
}
