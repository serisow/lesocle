<?php
namespace Drupal\poll\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\poll\Entity\Poll;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for poll add and edit forms.
 */
abstract class PollFormBase extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\poll\Entity\PollInterface
   */
  protected $entity;

  /**
   * The poll entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $pollStorage;

  /**
   * Constructs a base class for poll add and edit forms.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $poll_storage
   *   The poll entity storage.
   */
  public function __construct(EntityStorageInterface $poll_storage) {
    $this->pollStorage = $poll_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('poll')
    );
  }

  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Poll name'),
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this->pollStorage, 'load'],
      ],
      '#default_value' => $this->entity->id(),
      '#required' => TRUE,
      '#disabled' => !$this->entity->isNew(),
    ];
    $form['instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Poll Instructions'),
      '#description' => $this->t('Provide instructions for the poll takers.'),
      '#default_value' => $this->entity->getInstructions(),
      '#required' => TRUE,
    ];

    $form['langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Poll Language'),
      '#default_value' => $this->entity->getLangcode(),
      '#languages' => LanguageInterface::STATE_ALL,
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Poll Status'),
      '#options' => [
        Poll::STATUS_INACTIVE => $this->t('Inactive'),
        Poll::STATUS_ACTIVE => $this->t('Active'),
        Poll::STATUS_CLOSED => $this->t('Closed'),
      ],
      '#default_value' => $this->entity->isNew() ? Poll::STATUS_INACTIVE : $this->entity->getStatus(),
      '#description' => $this->t('Select the current status of the poll.'),
    ];

    if ($this->entity->isClosed()) {
      $form['status']['#disabled'] = TRUE;
      $form['status']['#description'] = $this->t('This poll is closed and cannot be reopened.');
    }

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
    if ($route_name == 'entity.poll.edit_questions') {
      // If we're on the Questions tab, redirect back to it
      $form_state->setRedirectUrl(Url::fromRoute('entity.poll.edit_questions', ['poll' => $this->entity->id()]));
    } else {
      // Otherwise, use the default redirect to the edit form
      $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    }
  }

}
