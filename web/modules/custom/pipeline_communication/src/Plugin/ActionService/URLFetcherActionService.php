<?php
namespace Drupal\pipeline_communication\Plugin\ActionService;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;

/**
 * @ActionService(
 *   id = "url_fetcher",
 *   label = @Translation("URL Fetcher")
 * )
 */
class URLFetcherActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {

  protected $httpClient;
  protected $loggerFactory;

  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('logger.factory')
    );
  }


  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration) {
    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $configuration['url'] ?? '',
      '#description' => $this->t('Enter the URL of the page to fetch.'),
      '#required' => TRUE,
    ];

    // Add content extraction options
    $form['extract_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Extract main content'),
      '#default_value' => $configuration['extract_content'] ?? TRUE,
      '#description' => $this->t('Extract meaningful content from the page instead of returning raw HTML.'),
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    return [
      'url' => $form_state->getValue('url'),
      'extract_content' => $form_state->getValue('extract_content'),
    ];
  }

  protected function extractMainContent($html) {
    // Reusing the content extraction logic from GoogleSearchStep
    $dom = new \DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new \DOMXPath($dom);

    // Common content selectors (same as in GoogleSearchStep)
    $content_nodes = $xpath->query('//article | //div[@class="content"] | //div[@id="content"] | //div[@class="post"] | //main | //div[@class="entry-content"] | //div[@class="post-content"] | //div[@class="blog-post"] | //div[@id="primary"] | //div[@id="main-content"] | //div[@class="text"] | //div[@class="text-content"] | //div[@id="body-content"] | //div[@class="post-article"]');

    if ($content_nodes->length > 0) {
      $content = $content_nodes->item(0)->textContent;
      // Clean up the content
      $content = preg_replace('/\s+/', ' ', $content);
      $content = trim($content);

      return [
        'title' => $this->extractTitle($dom),
        'content' => $content,
        //'url' => $url,
        'extracted_at' => \Drupal::time()->getCurrentTime(),
      ];
    }

    return null;
  }

  protected function extractTitle(\DOMDocument $dom) {
    $titles = $dom->getElementsByTagName('title');
    if ($titles->length > 0) {
      return trim($titles->item(0)->textContent);
    }
    return '';
  }

  public function executeAction(array $config, array &$context): string {
    try {
      $url = $config['configuration']['url'];
      if (empty($url)) {
        throw new \Exception('URL is required.');
      }

      $response = $this->httpClient->get($url);
      $content = (string) $response->getBody();

      if (!empty($config['configuration']['extract_content'])) {
        $extracted = $this->extractMainContent($content);
        if ($extracted) {
          return json_encode($extracted);
        }
      }

      // If extraction fails or is disabled, return raw content
      return $content;

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('URL fetch error: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }
}
