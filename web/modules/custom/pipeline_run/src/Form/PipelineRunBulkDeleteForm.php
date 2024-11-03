<?php
namespace Drupal\pipeline_run\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting pipeline runs in bulk.
 */
class PipelineRunBulkDeleteForm extends FormBase
{

  protected $entityTypeManager;
  protected $messenger;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger)
  {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  public function getFormId()
  {
    return 'pipeline_run_bulk_delete_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['runs'] = [
      '#type' => 'tableselect',
      '#header' => [
        'id' => $this->t('ID'),
        'pipeline' => $this->t('Pipeline'),
        'status' => $this->t('Status'),
        'start_time' => $this->t('Start Time'),
      ],
      '#options' => $this->getRunOptions(),
      '#empty' => $this->t('No pipeline runs found'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete selected runs'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  protected function getRunOptions()
  {
    $options = [];
    $storage = $this->entityTypeManager->getStorage('pipeline_run');
    $pipeline_storage = $this->entityTypeManager->getStorage('pipeline');

    $query = $storage->getQuery()
      ->accessCheck()
      ->sort('start_time', 'DESC');
    $ids = $query->execute();

    $runs = $storage->loadMultiple($ids);
    foreach ($runs as $run) {
      $pipeline = $pipeline_storage->load($run->getPipelineId());
      $options[$run->id()] = [
        'id' => $run->id(),
        'pipeline' => $pipeline ? $pipeline->label() : $this->t('N/A'),
        'status' => $run->getStatus(),
        'start_time' => \Drupal::service('date.formatter')->format($run->getStartTime(), 'short'),
      ];
    }

    return $options;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $selected = array_filter($form_state->getValue('runs'));
    if (empty($selected)) {
      $this->messenger->addWarning($this->t('No runs selected.'));
      return;
    }

    $storage = $this->entityTypeManager->getStorage('pipeline_run');
    $runs = $storage->loadMultiple($selected);
    $storage->delete($runs);

    $this->messenger->addMessage($this->t('Deleted @count pipeline runs.', [
      '@count' => count($selected),
    ]));
  }
}
