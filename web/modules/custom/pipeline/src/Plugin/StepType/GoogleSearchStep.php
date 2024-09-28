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
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state)
  {
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['query'] = $form_state->getValue(['data', 'query']);
    $this->configuration['category'] = $form_state->getValue(['data', 'category']);
  }

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string {
    $config = $this->getConfiguration()['data'];
    $pipeline_config = $this->configFactory->get('pipeline.settings');
    $api_key = $pipeline_config->get('google_custom_search_api_key');
    $cx = $pipeline_config->get('google_custom_search_engine_id');

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
      'num' => 5, // Default to 5 results
    ];

    $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query($params);

    try {
      $response = $this->httpClient->get($url);
      $data = json_decode($response->getBody(), TRUE);

      if (isset($data['items']) && !empty($data['items'])) {
        $search_results = array_map(function ($item) {
          return [
            'title' => $item['title'],
            'link' => $item['link'],
            'snippet' => $item['snippet'],
          ];
        }, $data['items']);

        $result = json_encode($search_results);
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
