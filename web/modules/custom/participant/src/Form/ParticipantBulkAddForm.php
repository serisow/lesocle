<?php
namespace Drupal\participant\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class ParticipantBulkAddForm extends FormBase {

  public function getFormId() {
    return 'participant_bulk_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $poll_id = NULL) {
    $form_state->set('poll_id', $poll_id);

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];

    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];


    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Participant'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
      ],
    ];

    $form['#attached']['library'][] = 'poll/poll_participant';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $form;
  }

  /**
   * {@inheritdoc}
   * @TODO: SSOW - REVOIR LA VALIDATION
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $participants = $form_state->getValue('participants');
    foreach ($participants as $delta => $participant) {
      if (empty($participant['first_name'])) {
        $form_state->setErrorByName("participants][$delta][first_name", $this->t('First name is required.'));
      }
      if (empty($participant['last_name'])) {
        $form_state->setErrorByName("participants][$delta][last_name", $this->t('Last name is required.'));
      }
      if (empty($participant['email'])) {
        $form_state->setErrorByName("participants][$delta][email", $this->t('Email is required.'));
      } elseif (!filter_var($participant['email'], FILTER_VALIDATE_EMAIL)) {
        $form_state->setErrorByName("participants][$delta][email", $this->t('Invalid email format.'));
      }
    }
  }
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $poll_id = $form_state->get('poll_id');
    $values = $form_state->getValues();

    $participant = \Drupal::entityTypeManager()->getStorage('participant')->create([
      'first_name' => $values['first_name'],
      'last_name' => $values['last_name'],
      'email' => $values['email'],
      'poll' => $poll_id,
      'status' => 'pending',
      'access_token' => \Drupal::service('uuid')->generate(),
    ]);
    $participant->save();

    $form_state->setRebuild(TRUE);
  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      return $response->addCommand(new ReplaceCommand('#participant-add-form', $form));
    } else {
      $poll_id = $form_state->get('poll_id');
      $url = Url::fromRoute('entity.poll.participants', ['poll' => $poll_id]);
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand($url->toString()));
    }

    return $response;
  }
}
