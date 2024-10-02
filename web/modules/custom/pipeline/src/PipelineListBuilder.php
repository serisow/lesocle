<?php
namespace Drupal\pipeline;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of pipeline entities.
 *
 * @see \Drupal\pipeline\Entity\Pipeline
 */
class PipelineListBuilder extends ConfigEntityListBuilder {
  /**
   * A form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;


  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigEntityStorageInterface $storage, FormBuilderInterface $form_builder) {
    $this->entityTypeId = $entity_type->id();
    $this->storage = $storage;
    $this->entityType = $entity_type;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Title');
    $header['status'] = $this->t('Status');
    $header['step_count'] = $this->t('Number of Steps');
    $header['langcode'] = $this->t('Language');
    $header['created'] = $this->t('Created');
    $header['changed'] = $this->t('Last Modified');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['status'] = $entity->isEnabled() ? $this->t('Enabled') : $this->t('Disabled');
    $row['step_count'] = $entity->getStepCount();
    $language = $entity->language();
    $row['langcode'] = $language ? $language->getName() : $this->t('Unknown');
    $row['created'] = $entity->getCreatedTime() ? \Drupal::service('date.formatter')->format($entity->getCreatedTime(), 'short') : '-';
    $row['changed'] = $entity->getChangedTime() ? \Drupal::service('date.formatter')->format($entity->getChangedTime(), 'short') : '-';
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    // Remove destination URL from the edit link to allow editing step types.
    $operations = parent::getDefaultOperations($entity);
    if (isset($operations['edit'])) {
      $operations['edit']['url'] = $entity->toUrl('edit-form');
    }
    $operations['export'] = [
      'title' => $this->t('Export'),
      'weight' => 10,
      'url' => Url::fromRoute('entity.pipeline.export', ['pipeline' => $entity->id()]),
    ];

    return $operations;
  }
  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = [];

    // Build the filter form.
    $form = $this->formBuilder->getForm('Drupal\pipeline\Form\PipelineFilterForm');
    $build['filter_form'] = $form;

    // Add the parent render array (the table).
    $build += parent::render();

    $build['table']['#empty'] = $this->t('There are currently no pipelines.');

    return $build;
  }

  public function load() {
    // Load all pipeline entities.
    $entity_ids = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'))
      ->execute();

    // Load the entities.
    $entities = $this->storage->loadMultiple($entity_ids);

    // Get filter values from the request.
    $title_filter = \Drupal::request()->query->get('title');
    $status_filter = \Drupal::request()->query->get('status');
    $langcode_filter = \Drupal::request()->query->get('langcode');

    // Filter the entities in PHP.
    $filtered_entities = [];
    /** @var \Drupal\pipeline\Entity\PipelineInterface $entity */
    foreach ($entities as $entity_id => $entity) {
      $match = TRUE;

      // Filter by title.
      if (!empty($title_filter)) {
        if (stripos($entity->label(), $title_filter) === FALSE) {
          $match = FALSE;
        }
      }

      // Filter by status.
      if ($status_filter !== NULL && $status_filter !== '') {
        // Ensure the status is cast to the correct type.
        $status = $entity->status() ? '1' : '0';
        if ($status !== $status_filter) {
          $match = FALSE;
        }
      }

      // Filter by language.
      if ($langcode_filter !== NULL && $langcode_filter !== '') {
        if ($entity->language()->getId() !== $langcode_filter) {
          $match = FALSE;
        }
      }

      if ($match) {
        $filtered_entities[$entity_id] = $entity;
      }
    }

    // Optional: Sort entities by label if needed.
    uasort($filtered_entities, [$this->entityType->getClass(), 'sort']);

    return $filtered_entities;
  }

}
