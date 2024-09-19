<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\pipeline\Entity\Pipeline;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for pipeline add and edit forms.
 */
abstract class PipelineFormBase extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\pipeline\Entity\PipelineInterface
   */
  protected $entity;

  /**
   * The pipeline entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $pipelineStorage;

  /**
   * Constructs a base class for pipeline add and edit forms.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $pipeline_storage
   *   The pipeline entity storage.
   */
  public function __construct(EntityStorageInterface $pipeline_storage) {
    $this->pipelineStorage = $pipeline_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('pipeline')
    );
  }

  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pipeline name'),
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this->pipelineStorage, 'load'],
      ],
      '#default_value' => $this->entity->id(),
      '#required' => TRUE,
      '#disabled' => !$this->entity->isNew(),
    ];
    $form['instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pipeline Instructions'),
      '#description' => $this->t('Provide instructions for the pipeline takers.'),
      '#default_value' => $this->entity->getInstructions(),
      '#required' => TRUE,
    ];

    $form['langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Pipeline Language'),
      '#default_value' => $this->entity->getLangcode(),
      '#languages' => LanguageInterface::STATE_ALL,
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Pipeline Status'),
      '#options' => [
        Pipeline::STATUS_INACTIVE => $this->t('Inactive'),
        Pipeline::STATUS_ACTIVE => $this->t('Active'),
      ],
      '#default_value' => $this->entity->isNew() ? Pipeline::STATUS_INACTIVE : $this->entity->getStatus(),
      '#description' => $this->t('Select the current status of the pipeline.'),
    ];

    $form['scheduled_time'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Schedule Execution'),
      '#description' => $this->t('Set a date and time for scheduled execution. Leave blank for immediate execution.'),
      '#default_value' => $this->entity->getScheduledTime() ? DrupalDateTime::createFromTimestamp($this->entity->getScheduledTime()) : NULL,
      '#date_time_element' => 'time',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $new_status = $form_state->getValue('status');

    // Set the new status before saving
    $entity->setStatus($new_status);
    $result = parent::save($form, $form_state);

    // Determine which tab we're on
    $route_name = $this->getRouteMatch()->getRouteName();
    if ($route_name == 'entity.pipeline.edit_steps') {
      // If we're on the Steps tab, redirect back to it
      $form_state->setRedirectUrl(Url::fromRoute('entity.pipeline.edit_steps', ['pipeline' => $this->entity->id()]));
    } else {
      // Otherwise, use the default redirect to the edit form
      $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $scheduled_time = $form_state->getValue('scheduled_time');
    if ($scheduled_time instanceof DrupalDateTime) {
      $this->entity->setScheduledTime($scheduled_time->getTimestamp());
    } else {
      $this->entity->setScheduledTime(NULL);
    }
  }

}
