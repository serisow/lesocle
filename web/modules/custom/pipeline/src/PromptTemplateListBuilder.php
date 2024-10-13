<?php
namespace Drupal\pipeline;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a list controller for the Prompt Template entity.
 */
class PromptTemplateListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Prompt Template Name');
    $header['description'] = $this->t('Description');
    $header['output_format'] = $this->t('Output Format');
    $header['operations'] = $this->t('Operations');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\pipeline\Entity\PromptTemplate $entity */
    $row['label'] = $entity->toLink($entity->label());
    $row['description'] = $entity->getDescription();
    $row['output_format'] = $entity->getOutputFormat();
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
    $build['table']['#empty'] = $this->t('There are currently no prompt templates. <a href=":url">Add a prompt template</a>.', [
      ':url' => Url::fromRoute('entity.prompt_template.add_form')->toString(),
    ]);
    return $build;
  }
}
