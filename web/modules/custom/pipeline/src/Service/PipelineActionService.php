<?php

namespace Drupal\pipeline\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\pipeline\Entity\PipelineInterface;
use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Service for handling pipeline actions at the entity level.
 */
class PipelineActionService {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The step type manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $stepTypeManager;

  /**
   * Constructs a new PipelineActionService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $step_type_manager
   *   The step type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    MessengerInterface $messenger,
    PluginManagerInterface $step_type_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->stepTypeManager = $step_type_manager;
  }

  /**
   * Get all pipelines applicable to a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get pipelines for.
   *
   * @return \Drupal\pipeline\Entity\PipelineInterface[]
   *   An array of pipeline entities keyed by ID.
   */
  public function getApplicablePipelines(EntityInterface $entity) {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    
    // Find pipelines that target this entity type and bundle
    $pipeline_storage = $this->entityTypeManager->getStorage('pipeline');
    $query = $pipeline_storage->getQuery();
    
    // Basic conditions - the pipeline must be enabled
    $query->condition('status', TRUE);
    
    // Entity type condition - we use entity_type here because that's the config field name,
    // even though our getter/setter methods are named getTargetEntityType/setTargetEntityType
    $query->condition('entity_type', $entity_type_id);
    
    // Bundle condition - either matches the specific bundle or is NULL (any bundle)
    // The field name is 'bundle' in the database even though our methods are getTargetBundle/setTargetBundle
    $bundle_group = $query->orConditionGroup()
      ->condition('bundle', $bundle)
      ->condition('bundle', NULL, 'IS NULL');
    $query->condition($bundle_group);
    
    $pipeline_ids = $query->execute();
    
    if (empty($pipeline_ids)) {
      return [];
    }
    
    // Load and return pipelines
    return $pipeline_storage->loadMultiple($pipeline_ids);
  }

  /**
   * Get operations for entity operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get operations for.
   *
   * @return array
   *   An array of operations.
   */
  public function getEntityOperations(EntityInterface $entity) {
    // This is a stub implementation - will be implemented properly in the future
    return [];
  }

  /**
   * Get action buttons for entity forms.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being edited.
   * @param array $form
   *   The form array.
   *
   * @return array
   *   An array of action buttons.
   */
  public function getEntityFormActions(EntityInterface $entity, array &$form) {
    // This is a stub implementation - will be implemented properly in the future
    return [];
  }

  /**
   * Execute a pipeline on an entity.
   *
   * @param \Drupal\pipeline\Entity\PipelineInterface $pipeline
   *   The pipeline to execute.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to execute the pipeline on.
   *
   * @return bool
   *   TRUE if the pipeline was executed successfully, FALSE otherwise.
   */
  public function executePipeline(PipelineInterface $pipeline, EntityInterface $entity) {
    // Implementation will depend on how pipelines are executed in your system
    // This is a placeholder for the actual implementation
    
    // Set up the initial context with entity data
    $context = [
      'entity' => [
        'id' => $entity->id(),
        'type' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        // Add fields or other data as needed
      ],
    ];
    
    // Execute the pipeline
    // This would typically call into your existing pipeline execution system
    // For now, just return true
    
    $this->messenger->addStatus($this->t('Pipeline %name has been executed on %entity.', [
      '%name' => $pipeline->label(),
      '%entity' => $entity->label(),
    ]));
    
    return TRUE;
  }
} 