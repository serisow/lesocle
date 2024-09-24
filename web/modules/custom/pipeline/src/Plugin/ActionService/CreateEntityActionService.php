<?php
namespace Drupal\pipeline\Plugin\ActionService;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\file\FileRepositoryInterface;
use Drupal\pipeline\Plugin\ActionServiceInterface;
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
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
      $container->get('entity_type.bundle.info')
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
    $content = $context['last_response'] ?? '';
    // Remove the ```json and ``` markers if they exist
    $content = preg_replace('/^```json\s*|\s*```$/s', '', $content);
    // Decode the JSON content
    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON format: " . json_last_error_msg());
    }

    if (!isset($data['title']) || !isset($data['body'])) {
      throw new \Exception("JSON must contain 'title' and 'body' fields");
    }

    $title = $data['title'];
    $body = $data['body'];

    // Ensure title is not empty and not too long
    $title = !empty($title) ? $title : 'Untitled Article';
    $title = substr($title, 0, 255);

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->create([
      'type' => 'article',
      'title' => $title,
      'body' => [
        'value' => $body,
        'format' => 'full_html',
      ],
    ]);
    $node->save();

    return "Created new article with ID: " . $node->id();
  }
}
