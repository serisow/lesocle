<?php
namespace Drupal\pipeline_integration\Plugin\ActionService;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\pipeline\Service\MediaCreationService;
use Drupal\pipeline_integration\EntityCreation\EntityCreationStrategyManager;
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
   * The strategy manager service.
   * @var \Drupal\pipeline_integration\EntityCreation\EntityCreationStrategyManager
   */
  protected $entityCreationStrategyManager;



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
    MediaCreationService $media_creation_service,
    EntityCreationStrategyManager $strategy_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->mediaCreationService = $media_creation_service;
    $this->entityCreationStrategyManager = $strategy_manager;
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
      $container->get('pipeline.media_creation_service'), // @TODO: Look if same purpose as MediaEntityCreator here
      $container->get('pipeline_integration.entity_creation_strategy_manager')
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
    $strategy = $this->entityCreationStrategyManager->getStrategy(
      $config['configuration']['entity_type'],
      $config['configuration']['entity_bundle']
    );

    if (!$strategy) {
      throw new \Exception("No strategy found for {$config['entity_type']} - {$config['entity_bundle']}");
    }
    return json_encode($strategy->createEntity($context['results'], $context));
  }
}
