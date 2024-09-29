<?php
namespace Drupal\pipeline\Plugin\StepType;

use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a 'Google Search' step type.
 *
 * @StepType(
 *   id = "google_search",
 *   label = @Translation("Google Search Step"),
 *   description = @Translation("Performs a Google search and returns the results.")
 * )
 */
class GoogleSearchStep extends ConfigurableStepTypeBase implements StepTypeExecutableInterface {

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
  protected function additionalDefaultConfiguration()
  {
    return [
        'query' => '',
        'category' => '',
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
      '#description' => $this->t('Enter the search query.'),
      '#required' => TRUE,
    ];

    $form['category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Category'),
      '#default_value' => $this->configuration['category'],
      '#description' => $this->t('Enter a category to refine the search (optional).'),
    ];

    // Advanced parameters in a collapsible fieldset
    $form['advanced_params'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Parameters'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['advanced_params']['num_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of Results'),
      '#default_value' => $this->configuration['advanced_params']['num_results'] ?? 10,
      '#min' => 1,
      '#max' => 10,
      '#description' => $this->t('Number of search results to return (1-10).'),
    ];

    $form['advanced_params']['date_restrict'] = [
      '#type' => 'select',
      '#title' => $this->t('Date Restriction'),
      '#options' => [
        '' => $this->t('No restriction'),
        'd1' => $this->t('Past 24 hours'),
        'w1' => $this->t('Past week'),
        'm1' => $this->t('Past month'),
        'y1' => $this->t('Past year'),
      ],
      '#default_value' => $this->configuration['advanced_params']['date_restrict'] ?? '',
      '#description' => $this->t('Restrict results to a specific time frame.'),
    ];

    $form['advanced_params']['sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort Order'),
      '#options' => [
        '' => $this->t('Relevance'),
        'date' => $this->t('Date'),
      ],
      '#default_value' => $this->configuration['advanced_params']['sort'] ?? '',
      '#description' => $this->t('Sort results by relevance or date.'),
    ];

    $form['advanced_params']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $this->getLanguageOptions(),
      '#default_value' => $this->configuration['advanced_params']['language'] ?? '',
      '#description' => $this->t('Restrict results to documents written in a specific language.'),
    ];

    $form['advanced_params']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $this->getCountryOptions(),
      '#default_value' => $this->configuration['advanced_params']['country'] ?? '',
      '#description' => $this->t('Restrict results to a specific country.'),
    ];

    $form['advanced_params']['site_search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site Search'),
      '#default_value' => $this->configuration['advanced_params']['site_search'] ?? '',
      '#description' => $this->t('Restrict results to a specific site (e.g., example.com).'),
    ];

    $form['advanced_params']['file_type'] = [
      '#type' => 'select',
      '#title' => $this->t('File Type'),
      '#options' => $this->getFileTypeOptions(),
      '#default_value' => $this->configuration['advanced_params']['file_type'] ?? '',
      '#description' => $this->t('Restrict results to a specific file type.'),
    ];

    $form['advanced_params']['safe_search'] = [
      '#type' => 'select',
      '#title' => $this->t('Safe Search'),
      '#options' => [
        '' => $this->t('Off'),
        'medium' => $this->t('Medium'),
        'high' => $this->t('High'),
      ],
      '#default_value' => $this->configuration['advanced_params']['safe_search'] ?? '',
      '#description' => $this->t('Filtering level for explicit content.'),
    ];

    return $form;
  }

  private function getLanguageOptions() {
    return [
      '' => $this->t('Any language'),
      'lang_en' => $this->t('English'),
      'lang_fr' => $this->t('French'),
      'lang_de' => $this->t('German'),
      'lang_es' => $this->t('Spanish'),
      // Add more languages as needed
    ];
  }

  private function getCountryOptions() {
    return [
      '' => $this->t('Any country'),
      'countryUS' => $this->t('United States'),
      'countryGB' => $this->t('United Kingdom'),
      'countryFR' => $this->t('France'),
      'countryDE' => $this->t('Germany'),
      // Add more countries as needed
    ];
  }

  private function getFileTypeOptions() {
    return [
      '' => $this->t('Any file type'),
      'pdf' => $this->t('PDF'),
      'doc' => $this->t('Word Document'),
      'xls' => $this->t('Excel Spreadsheet'),
      'ppt' => $this->t('PowerPoint'),
      'jpg' => $this->t('JPEG Image'),
      // Add more file types as needed
    ];
  }
  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['query'] = $form_state->getValue(['data', 'query']);
    $this->configuration['category'] = $form_state->getValue(['data', 'category']);
    $this->configuration['num_results'] = $form_state->getValue(['data', 'advanced_params', 'num_results']);
    $this->configuration['date_restrict'] = $form_state->getValue(['data', 'advanced_params', 'date_restrict']);
    $this->configuration['sort'] = $form_state->getValue(['data', 'advanced_params', 'sort']);
    $this->configuration['language'] = $form_state->getValue(['data', 'advanced_params', 'language']);
    $this->configuration['country'] = $form_state->getValue(['data', 'advanced_params', 'country']);
    $this->configuration['site_search'] = $form_state->getValue(['data', 'advanced_params', 'site_search']);
    $this->configuration['file_type'] = $form_state->getValue(['data', 'advanced_params', 'file_type']);
    $this->configuration['safe_search'] = $form_state->getValue(['data', 'advanced_params', 'safe_search']);
  }

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string {
    $config = $this->getConfiguration()['data'];
    $google_config = $this->configFactory->get('pipeline.google_settings');
    $api_key = $google_config->get('google_custom_search_api_key');
    $cx = $google_config->get('google_custom_search_engine_id');

    if (!$api_key || !$cx) {
      throw new \Exception("Google Custom Search API key or Search Engine ID is not configured.");
    }

    $query = $config['query'];
    $category = $config['category'];

    // Retrieve the results from previous steps
    $results = $context['results'] ?? [];

    // Find the last non-empty response in the results
    $last_response = '';
    if (!empty($results)) {
      $last_response = end($results);
    }

    // Use last_response to potentially modify the query if needed
    $query = $this->replacePlaceholders($query, ['last_response' => $last_response]);

    // Combine query and category
    $combined_query = trim($query . ' ' . $category);

    $params = [
      'key' => $api_key,
      'cx' => $cx,
      'q' => $combined_query,
      'num' => $config['num_results'],
    ];

    if (!empty($this->configuration['date_restrict'])) {
      $params['dateRestrict'] = $this->configuration['date_restrict'];
    }

    if (!empty($this->configuration['sort'])) {
      $params['sort'] = $this->configuration['sort'];
    }

    if (!empty($this->configuration['language'])) {
      $params['lr'] = $this->configuration['language'];
    }

    if (!empty($this->configuration['country'])) {
      $params['cr'] = $this->configuration['country'];
    }

    if (!empty($this->configuration['site_search'])) {
      $params['siteSearch'] = $this->configuration['site_search'];
    }

    if (!empty($this->configuration['file_type'])) {
      $params['fileType'] = $this->configuration['file_type'];
    }

    if (!empty($this->configuration['safe_search'])) {
      $params['safe'] = $this->configuration['safe_search'];
    }

    $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query($params);

    try {
      $response = $this->httpClient->get($url);
      $data = json_decode($response->getBody(), TRUE);

      if (isset($data['items']) && !empty($data['items'])) {
        $enriched_results = [];

        foreach ($data['items'] as $item) {
          $enriched_result = [
            'title' => $item['title'],
            'link' => $item['link'],
            'snippet' => $item['snippet'],
            'expanded_content' => $this->fetchExpandedContent($item['link']),
          ];
          $enriched_results[] = $enriched_result;
        }

        $result = json_encode($enriched_results);
        $this->configuration['response'] = $result;
      } else {
        $result = json_encode(['message' => 'No results found.']);
      }

      // Store the result in the context
      if ($config['step_output_key']) {
        $context['results'][$config['step_output_key']] = $result;
      }

      return $result;
    } catch (\Exception $e) {
      throw new \Exception("Error fetching search results: " . $e->getMessage());
    }
  }

  private function fetchExpandedContent($url) {
    try {
      $response = $this->httpClient->get($url);
      $html = $response->getBody()->getContents();

      // Use a library like QueryPath or Symfony DomCrawler for better HTML parsing
      $dom = new \DOMDocument();
      @$dom->loadHTML($html);
      $xpath = new \DOMXPath($dom);

      // Extract main content (this is a simple example, might need adjustment based on site structure)
      $content_nodes = $xpath->query('//article | //div[@class="content"] | //div[@id="content"] | //main | //div[@class="post"] | //div[@id="main"] | //div[@class="entry-content"] | //div[@class="post-content"] | //div[@class="blog-post"] | //div[@id="primary"] | //div[@id="main-content"] | //div[@class="text"] | //div[@class="text-content"] | //div[@id="body-content"] | //div[@class="post-article"]');
      if ($content_nodes->length > 0) {
        $content = $content_nodes->item(0)->textContent;
        // Clean up the content
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        // Limit to about 1000 characters, but don't cut words
        if (strlen($content) > 2000) {
          $content = substr($content, 0, 2000);
          $content = substr($content, 0, strrpos($content, ' ')) . '...';
        }
        return $content;
      }
    } catch (\Exception $e) {
      // Log the error
      return "Error fetching expanded content: " . $e->getMessage();
    }
    return "No expanded content available.";
  }

  /**
   * Replaces placeholders in the query with values from previous steps.
   *
   * @param string $query
   *   The query string with placeholders.
   * @param array $data
   *   The data array containing results from previous steps.
   *
   * @return string
   *   The query with placeholders replaced.
   */
  protected function replacePlaceholders(string $query, array $data): string {
    return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($data) {
      $key = $matches[1];
      return isset($data[$key]) ? $data[$key] : $matches[0];
    }, $query);
  }
}
