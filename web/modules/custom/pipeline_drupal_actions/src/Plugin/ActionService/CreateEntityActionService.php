<?php
namespace Drupal\pipeline_drupal_actions\Plugin\ActionService;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\pipeline\Service\MediaCreationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "create_entity",
 *   label = @Translation("Create Entity Action")
 * )
 */
class CreateEntityActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The media creation service.
   * @var \Drupal\pipeline\Service\MediaCreationService
   */
  protected $mediaCreationService;


  /**
   * Constructs a CreateEntityActionService object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\pipeline\Service\MediaCreationService $media_action_service
   *   The media creation service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    MediaCreationService $media_creation_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->mediaCreationService = $media_creation_service;
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
      $container->get('entity_type.bundle.info'),
      $container->get('pipeline.media_creation_service')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration) {
    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#options' => $this->getEntityTypeOptions(),
      '#default_value' => $configuration['entity_type'] ?? '',
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'updateEntityBundleOptions'],
        'wrapper' => 'entity-bundle-wrapper',
        'event' => 'change',
      ],
    ];

    $form['entity_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Bundle'),
      '#options' => $this->getEntityBundleOptions($form_state, $configuration['entity_type'] ?? ''),
      '#default_value' => $configuration['entity_bundle'] ?? '',
      '#required' => TRUE,
      '#prefix' => '<div id="entity-bundle-wrapper">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    return [
      'entity_type' => $form_state->getValue('entity_type'),
      'entity_bundle' => $form_state->getValue('entity_bundle'),
    ];
  }

  /**
   * Ajax callback to update entity bundle options.
   */
  public function updateEntityBundleOptions(array &$form, FormStateInterface $form_state) {
    return $form['configuration']['entity_bundle'];
  }

  /**
   * Get available entity type options.
   */
  protected function getEntityTypeOptions() {
    $options = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
        $options[$entity_type_id] = $entity_type->getLabel();
      }
    }
    return $options;
  }

  /**
   * Get available entity bundle options.
   */
  protected function getEntityBundleOptions(FormStateInterface $form_state, $default_entity_type = '') {
    $options = [];
    $entity_type = $form_state->getValue(['configuration', 'entity_type']) ?: $default_entity_type;

    if ($entity_type) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
      foreach ($bundles as $bundle_id => $bundle_info) {
        $options[$bundle_id] = $bundle_info['label'];
      }
    }

    if (empty($options)) {
      $options[''] = $this->t('- Select an entity type first -');
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    // Find the article content
    $taxonomy_data = null;
    $article_content = null;
    foreach ($context['results'] as $step) {
      if ($step['output_type'] === 'article_content') {
        $article_content = $step['data'];
        break;
      }
      if ($step['output_type'] === 'taxonomy_term') {
        $taxonomy_data = $step['data'];
      }
    }

    if (!$article_content) {
      throw new \Exception("Article content not found in the context.");
    }

    // Remove ```json prefix and ``` suffix if present
    $content = preg_replace('/^```json\s*|\s*```$/s', '', $article_content);

    // Trim any whitespace
    $content = trim($content);

    // Decode the JSON content
    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON format: " . json_last_error_msg());
    }

    if (!isset($data['title']) || !isset($data['body'])) {
      throw new \Exception("JSON must contain 'title' and 'body' fields");
    }

    // Remove the first H1 tag and its contents from the body
    $data['body'] = preg_replace('/<h1>.*?<\/h1>/s', '', $data['body'], 1);

    // Trim any leading whitespace that might remain after removing the H1
    $data['body'] = ltrim($data['body']);

    // Find the featured image data
    $image_data = null;
    foreach ($context['results'] as $step) {
      if ($step['output_type'] === 'featured_image') {
        $image_data = $step['data'];
        break;
      }
    }

    // Create media entity if image info is available
    $media_id = null;
    if ($image_data) {
      $image_info = json_decode($image_data, true);
      if ($image_info) {
        $media_id = $this->mediaCreationService->createImageMedia($image_info);
      }
    }

    // Find SEO metadata
    $seo_content = null;
    foreach ($context['results'] as $step) {
      if ($step['output_type'] === 'seo_metadata') {
        $seo_data = preg_replace('/^```json\s*|\s*```$/s', '', $step['data']);
        $seo_content = json_decode(trim($seo_data), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
          throw new \Exception("Invalid JSON format: " . json_last_error_msg());
        }

        if (!isset($seo_content['title']) || !isset($seo_content['summary'])) {
          throw new \Exception("JSON must contain 'title' and 'summary' fields");
        }
        break;
      }
    }

    $title = $data['title'];
    $body = $data['body'];

    // Ensure title is not empty and not too long
    $title = !empty($title) ? $title : 'Untitled Article';
    $title = substr($title, 0, 255);

// Process taxonomy data
    $selected_terms = [];
    if ($taxonomy_data) {
      // Remove any potential JSON code block markers
      $taxonomy_data = preg_replace('/^```json\s*|\s*```$/s', '', $taxonomy_data);
      $taxonomy_data = trim($taxonomy_data);

      $taxonomy_content = json_decode($taxonomy_data, true);

      if (json_last_error() === JSON_ERROR_NONE && isset($taxonomy_content['selected_terms']) && is_array($taxonomy_content['selected_terms'])) {
        foreach ($taxonomy_content['selected_terms'] as $tid) {
          if (is_numeric($tid)) {
            $selected_terms[] = ['target_id' => (int)$tid];
          }
        }
      }
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->create([
      'type' => 'article',
      'title' => $seo_content['title'] ?? $title,
      'body' => [
        'value' => $body,
        'format' => 'full_html',
        'summary' => $seo_content['summary'] ?? '',
      ],
      'field_category' => $selected_terms, // Set the taxonomy terms
    ]);

    // Add the media to the article if available
    if ($media_id) {
      $node->field_media_image = ['target_id' => $media_id];
    }

    $node->save();

    return json_encode([
      'nid' => $node->id(),
      'title' => $node->getTitle(),
      'media_id' => $media_id,
    ]);
  }
}
