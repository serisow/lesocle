<?php

namespace Drupal\pipeline_run\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the Pipeline Run entity type.
 */
class PipelineRunListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * Constructs a new PipelineRunListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    DateFormatterInterface $date_formatter,
    RedirectDestinationInterface $redirect_destination,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('redirect.destination'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['pipeline'] = $this->t('Pipeline');
    $header['status'] = $this->t('Status');
    $header['start_time'] = $this->t('Start Time');
    $header['end_time'] = $this->t('End Time');
    $header['duration'] = $this->t('Duration');
    $header['created_by'] = $this->t('Created By');
    $header['triggered_by'] = $this->t('Triggered By');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\pipeline_run\Entity\PipelineRun $entity */
    $pipeline = $this->entityTypeManager->getStorage('pipeline')->load($entity->getPipelineId());
    $row['pipeline'] = $pipeline ? $pipeline->label() : $this->t('N/A');
    $row['status'] = $entity->getStatus();
    $row['start_time'] = $this->dateFormatter->format($entity->getStartTime(), 'short');
    $row['end_time'] = $entity->getEndTime() ? $this->dateFormatter->format($entity->getEndTime(), 'short') : $this->t('N/A');
    $row['duration'] = $entity->getDuration() ? $this->formatDuration($entity->getDuration()) : $this->t('N/A');
    $row['created_by'] = $entity->getCreatedBy() ? $entity->getCreatedBy()->getDisplayName() : $this->t('N/A');
    $row['triggered_by'] = $entity->getTriggeredBy();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    // Remove the 'edit' operation if it exists
    unset($operations['edit']);

    // Keep the 'delete' operation
    if (isset($operations['delete'])) {
      $destination = $this->redirectDestination->getAsArray();
      $operations['delete']['query'] = $destination;
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['bulk_delete'] = [
      '#type' => 'link',
      '#title' => $this->t('Delete multiple runs'),
      '#url' => Url::fromRoute('pipeline_run.bulk_delete'),
      '#attributes' => [
        'class' => ['button'],
      ],
      '#weight' => -10,
    ];

    // Remove the 'Add Pipeline Run' action button
    unset($build['table']['#header']['operations']);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_ids = $this->getEntityIds();
    return $this->storage->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->accessCheck()
      ->sort('end_time', 'DESC');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

  /**
   * Format duration in a human-readable format.
   *
   * @param int $seconds
   *   Duration in seconds.
   *
   * @return string
   *   Formatted duration string.
   */
  protected function formatDuration($seconds) {
    if ($seconds < 60) {
      return $this->t('@seconds sec', ['@seconds' => $seconds]);
    }
    $minutes = floor($seconds / 60);
    $remaining_seconds = $seconds % 60;
    return $this->t('@minutes min @seconds sec', [
      '@minutes' => $minutes,
      '@seconds' => $remaining_seconds,
    ]);
  }

}
