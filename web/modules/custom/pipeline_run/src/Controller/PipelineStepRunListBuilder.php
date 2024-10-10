<?php


namespace Drupal\pipeline_run\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the Pipeline Step Run entity type.
 */
class PipelineStepRunListBuilder extends EntityListBuilder
{

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new PipelineStepRunListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter)
  {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type)
  {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader()
  {
    $header['id'] = $this->t('ID');
    $header['pipeline_run'] = $this->t('Pipeline Run');
    $header['step_type'] = $this->t('Step Type');
    $header['status'] = $this->t('Status');
    $header['sequence'] = $this->t('Sequence');
    $header['start_time'] = $this->t('Start Time');
    $header['execution_time'] = $this->t('Execution Time');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity)
  {
    /** @var \Drupal\pipeline_run\Entity\PipelineStepRun $entity */
    $row['id'] = $entity->id();
    $row['pipeline_run'] = $entity->getPipelineRunId();
    $row['step_type'] = $entity->get('step_type')->value;
    $row['status'] = $entity->getStatus();
    $row['sequence'] = $entity->getSequence();
    $row['start_time'] = $this->dateFormatter->format($entity->getStartTime(), 'short');
    $row['execution_time'] = number_format($entity->getExecutionTime(), 2) . ' ' . $this->t('seconds');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity)
  {
    $operations = parent::getDefaultOperations($entity);

    // Remove the 'edit' operation as PipelineStepRun entities should not be editable
    unset($operations['edit']);

    // Optionally, you can add a 'view' operation if you want to provide a detailed view page
    $operations['view'] = [
      'title' => $this->t('View'),
      'weight' => 0,
      'url' => $entity->toUrl('canonical'),
    ];

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render()
  {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no pipeline step runs yet.');
    return $build;
  }

}
