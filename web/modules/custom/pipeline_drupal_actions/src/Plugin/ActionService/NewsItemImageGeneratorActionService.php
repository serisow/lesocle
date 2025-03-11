<?php
namespace Drupal\pipeline_drupal_actions\Plugin\ActionService;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\pipeline\Plugin\LLMServiceManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "news_item_image_generator",
 *   label = @Translation("News Item Image Generator")
 * )
 */
class NewsItemImageGeneratorActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface
{

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The LLM service manager.
   *
   * @var \Drupal\pipeline\Plugin\LLMServiceManager
   */
  protected $llmServiceManager;

  /**
   * Constructs a new ArticleImageGeneratorActionService object.
   */
  public function __construct(
    array                         $configuration,
                                  $plugin_id,
                                  $plugin_definition,
    EntityTypeManagerInterface    $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface        $config_factory,
    LLMServiceManager             $llm_service_manager
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->llmServiceManager = $llm_service_manager;
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
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('plugin.manager.llm_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration)
  {
    $form['image_generator'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Generator'),
      '#description' => $this->t('Select which service to use for image generation.'),
      '#options' => $this->getImageGeneratorOptions(),
      '#default_value' => $configuration['image_generator'] ?? 'openai_image',
      '#required' => TRUE,
    ];

    $form['image_config'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Configuration'),
      '#description' => $this->t('Select the LLM config to use for image generation.'),
      '#options' => $this->getLLMConfigOptions(),
      '#default_value' => $configuration['image_config'] ?? '',
      '#required' => TRUE,
    ];

    $form['image_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Size'),
      '#description' => $this->t('Select the size for generated images.'),
      '#options' => [
        '1024x1024' => $this->t('1024x1024 - Standard'),
        '1024x1792' => $this->t('1024x1792 - Portrait'),
        '1792x1024' => $this->t('1792x1024 - Landscape'),
      ],
      '#default_value' => $configuration['image_size'] ?? '1024x1024',
    ];

    $form['concurrent_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Concurrent Generation Limit'),
      '#description' => $this->t('Maximum number of images to generate concurrently. Use a lower number to avoid API rate limits.'),
      '#min' => 1,
      '#max' => 10,
      '#default_value' => $configuration['concurrent_limit'] ?? 3,
      '#required' => TRUE,
    ];

    $form['retry_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Retry Count'),
      '#description' => $this->t('Number of times to retry failed image generation.'),
      '#min' => 0,
      '#max' => 5,
      '#default_value' => $configuration['retry_count'] ?? 2,
    ];

    return $form;
  }

  /**
   * Gets options for image generators.
   */
  protected function getImageGeneratorOptions()
  {
    // For now we only have OpenAI, but this allows us to easily add more
    return [
      'openai_image' => $this->t('DALL-E (OpenAI)'),
      // Future options could include:
      // 'stability_ai' => $this->t('Stable Diffusion (Stability AI)'),
      // 'midjourney' => $this->t('Midjourney'),
    ];
  }

  /**
   * Gets options for LLM configs.
   */
  protected function getLLMConfigOptions()
  {
    $options = [];
    $llm_configs = $this->entityTypeManager->getStorage('llm_config')->loadMultiple();
    foreach ($llm_configs as $llm_config) {
      // Only include configs that match our image generation services
      if (in_array($llm_config->getModelName(), ['dall-e-3'])) {
        $options[$llm_config->id()] = $llm_config->label();
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    return [
      'image_generator' => $form_state->getValue('image_generator'),
      'image_config' => $form_state->getValue('image_config'),
      'image_size' => $form_state->getValue('image_size'),
      'concurrent_limit' => $form_state->getValue('concurrent_limit'),
      'retry_count' => $form_state->getValue('retry_count'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string
  {
    try {
      // Determine the LLM service to use based on the configuration
      $image_generator = $config['configuration']['image_generator'] ?? 'openai_image';
      $image_config_id = $config['configuration']['image_config'] ?? '';
      $image_size = $config['configuration']['image_size'] ?? '1024x1024';
      $concurrent_limit = $config['configuration']['concurrent_limit'] ?? 3;
      $retry_count = $config['configuration']['retry_count'] ?? 2;

      // Load the LLM configuration
      $llm_config = $this->entityTypeManager->getStorage('llm_config')->load($image_config_id);
      if (!$llm_config) {
        throw new \Exception('Image generation configuration not found: ' . $image_config_id);
      }

      // Get the LLM service
      $llm_service = $this->llmServiceManager->createInstance($image_generator);
      if (!$llm_service) {
        throw new \Exception('Image generation service not found: ' . $image_generator);
      }

      $news_items_data = $this->findNewsContentData($context);
      if (empty($news_items_data)) {
        throw new \Exception('Article data not found in context. Make sure an LLM step has generated article data first.');
      }

      // Process articles in batches based on concurrent_limit
      $enriched_articles = [];
      $articles_chunks = array_chunk($news_items_data, $concurrent_limit);

      foreach ($articles_chunks as $chunk) {
        $processed_chunk = [];

        foreach ($chunk as $article) {
          // Extract the image prompt
          $image_prompt = $article['image_prompt'] ?? '';
          if (empty($image_prompt)) {
            $this->loggerFactory->get('pipeline')->warning('Missing image prompt for article: @id', [
              '@id' => $article['article_id'] ?? 'unknown',
            ]);
            // Add article without image
            $article['image_info'] = null;
            $processed_chunk[] = $article;
            continue;
          }

          // Create a config array for the LLM service with the desired image size
          $llm_config_array = $llm_config->toArray();
          $llm_config_array['image_size'] = $image_size;

          // Generate the image
          $retries = 0;
          $success = false;
          $error_message = '';

          while (!$success && $retries <= $retry_count) {
            try {
              $image_result = $llm_service->callLLM($llm_config_array, $image_prompt);

              // Parse the result - it should be a JSON string with image file info
              $image_info = json_decode($image_result, true);
              if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse image generation result: ' . json_last_error_msg());
              }

              // Add the image info to the article
              $article['image_info'] = $image_info;
              $success = true;
            } catch (\Exception $e) {
              $error_message = $e->getMessage();
              $retries++;

              if ($retries <= $retry_count) {
                $this->loggerFactory->get('pipeline')->warning('Image generation retry @retry of @max for article @id: @error', [
                  '@retry' => $retries,
                  '@max' => $retry_count,
                  '@id' => $article['article_id'] ?? 'unknown',
                  '@error' => $error_message,
                ]);
                // Wait before retrying
                sleep(2);
              }
            }
          }

          // If all retries failed, log the error and continue
          if (!$success) {
            $this->loggerFactory->get('pipeline')->error('Image generation failed after @retries retries for article @id: @error', [
              '@retries' => $retry_count,
              '@id' => $article['article_id'] ?? 'unknown',
              '@error' => $error_message,
            ]);
            $article['image_info'] = null;
            $article['image_error'] = $error_message;
          }

          $processed_chunk[] = $article;
        }

        // Add the processed chunk to our results
        $enriched_articles = array_merge($enriched_articles, $processed_chunk);

        // Add a small delay between chunks to avoid rate limiting
        if (count($articles_chunks) > 1) {
          sleep(1);
        }
      }

      // Add the results to the context with the appropriate output_type
      $result = json_encode($enriched_articles);

      // Store the result in the context with a specific output_type
      /*$context['results'][$this->getStepOutputKey()] = [
        'output_type' => 'news_with_images',
        'service' => 'news_item_image_generator',
        'data' => $result,
      ];*/

      return $result;
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Article image generation failed: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Finds news content data with the expected output_type in the pipeline context.
   *
   * @param array $context
   *   The pipeline context.
   *
   * @return array
   *   Array of news items data.
   *
   * @throws \Exception
   *   If no valid news content data is found.
   */
  protected function findNewsContentData(array $context): array {
    foreach ($context['results'] as $step_key => $result) {
      // Check for the expected output_type
      if (isset($result['output_type']) && $result['output_type'] === 'structured_news') {
        if (!empty($result['data'])) {
          $data = $result['data'];

          // If it's a string, try to decode it as JSON
          if (is_string($data)) {
            // Clean the string by removing markdown code block delimiters
            $cleaned_data = preg_replace('/^```json\s*|\s*```$/s', '', $data);
            $cleaned_data = trim($cleaned_data);

            $decoded = json_decode($cleaned_data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
              return $decoded;
            }
          }
          // If it's already an array, return it
          elseif (is_array($data)) {
            return $data;
          }
        }
      }
    }

    throw new \Exception('No structured news content found in context. Make sure a previous step has output_type "structured_news".');
  }
}
