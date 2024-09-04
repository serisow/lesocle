<?php
namespace Drupal\poll;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\poll\Entity\Poll;
use Drupal\poll\Entity\PollInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of poll entities.
 *
 * @see \Drupal\poll\Entity\Poll
 */
class PollListBuilder extends ConfigEntityListBuilder {
  /**
   * A form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('form_builder')
    );
  }

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
  public function buildHeader() {
    $header['label'] = $this->t('Title');
    $header['status'] = $this->t('Status');
    $header['question_count'] = $this->t('Number of Questions');
    $header['participant_count'] = $this->t('Number of Participants');
    $header['langcode'] = $this->t('Language');
    $header['created'] = $this->t('Created');
    $header['changed'] = $this->t('Last Modified');
    $header['closed_date'] = $this->t('Closed Date');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['status'] = $this->t($entity->getStatus());
    $row['question_count'] = $entity->getQuestionCount();
    $row['participant_count'] = $this->getParticipantCount($entity);
    $language = $entity->language();
    $row['langcode'] = $language ? $language->getName() : $this->t('Unknown');
    $row['created'] = $entity->getCreatedTime() ? \Drupal::service('date.formatter')->format($entity->getCreatedTime(), 'short') : '-';
    $row['changed'] = $entity->getChangedTime() ? \Drupal::service('date.formatter')->format($entity->getChangedTime(), 'short') : '-';
    $row['closed_date'] = $entity->getStatus() === Poll::STATUS_CLOSED ?
      \Drupal::service('date.formatter')->format($entity->getClosedDate(), 'short') : $this->t('N/A');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    // Remove destination URL from the edit link to allow editing question types.
    $operations = parent::getDefaultOperations($entity);
    if (isset($operations['edit'])) {
      $operations['edit']['url'] = $entity->toUrl('edit-form');
    }
    $operations['export'] = [
      'title' => $this->t('Export'),
      'weight' => 10,
      'url' => Url::fromRoute('entity.poll.export', ['poll' => $entity->id()]),
    ];
    // Add new operation for viewing analysis
    $frontend_base_url = \Drupal::config('poll.settings')->get('frontend_base_url');
    if ($frontend_base_url && !empty($entity->getLlmAnalysis())) {
      $operations['view_analysis'] = [
        'title' => $this->t('View Analysis'),
        'weight' => 11,
        'url' => Url::fromUri($frontend_base_url . '/poll-analysis/' . $entity->id()),
        'attributes' => [
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
        ],
      ];
    }

    return $operations;
  }
  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are currently no polls.', [
      ':url' => Url::fromRoute('entity.poll.add_form')->toString(),
    ]);
    return $build;
  }

  protected function getParticipantCount(PollInterface $poll) {
    $participant_count = \Drupal::entityQuery('participant')
      ->accessCheck(TRUE)
      ->condition('poll', $poll->id())
      ->count()
      ->execute();
    return $participant_count;
  }

}
