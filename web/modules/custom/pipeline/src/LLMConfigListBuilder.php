<?php
namespace Drupal\pipeline;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a list controller for the LLM Config entity.
 */
class LLMConfigListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    // Define table headers for the list.
    $header['label'] = $this->t('LLM Config Name');
    $header['api_url'] = $this->t('API URL');
    $header['model_name'] = $this->t('Model Name');
    $header['model_version'] = $this->t('Model Version');
    $header['operations'] = $this->t('Operations');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    // Build a row of data for each LLM Config entity.
    /** @var \Drupal\pipeline\Entity\LLMConfig $entity */
    $row['label'] = $entity->toLink($entity->label());
    $row['api_url'] = $entity->getApiUrl();
    $row['model_name'] = $entity->getModelName();
    $row['model_version'] = $entity->getModelVersion();
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
    $build['table']['#empty'] = $this->t('There are currently no llm config.', [
      ':url' => Url::fromRoute('entity.llm_config.add_form')->toString(),
    ]);
    return $build;
  }

}
