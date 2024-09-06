<?php
namespace Drupal\pipeline;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;

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
    $row['operations'] = $this->buildOperations($entity);
    return $row + parent::buildRow($entity);
  }

}
