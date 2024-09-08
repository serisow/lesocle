<?php
namespace Drupal\pipeline;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of Action Config entities.
 */
class ActionConfigListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Action Config');
    $header['id'] = $this->t('Machine name');
    $header['action_type'] = $this->t('Action Type');
    $header['operations'] = $this->t('Operations');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\pipeline\Entity\ActionConfig $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['action_type'] = $this->getActionTypeLabel($entity->get('action_type'));
    return $row + parent::buildRow($entity);
  }

  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $operations['edit'] = [
      'title' => $this->t('Edit'),
      'weight' => 10,
      'url' => $entity->toUrl('edit-form'),
    ];
    $operations['delete'] = [
      'title' => $this->t('Delete'),
      'weight' => 100,
      'url' => $entity->toUrl('delete-form'),
    ];
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are currently no action config.', [
      ':url' => Url::fromRoute('entity.action_config.add_form')->toString(),
    ]);
    return $build;
  }

  /**
   * Gets the human-readable label for an action type.
   *
   * @param string $action_type
   *   The machine name of the action type.
   *
   * @return string
   *   The human-readable label of the action type.
   */
  protected function getActionTypeLabel($action_type) {
    $action_types = [
      'create_entity' => $this->t('Create Entity'),
      'update_entity' => $this->t('Update Entity'),
      'delete_entity' => $this->t('Delete Entity'),
      'call_api' => $this->t('Call External API'),
    ];
    return $action_types[$action_type] ?? $action_type;
  }

}
