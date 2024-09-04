<?php
namespace Drupal\participant\Form;

use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Url;
use Drupal\participant\Entity\Participant;
use Drupal\poll\Entity\PollInterface;

class ParticipantEditForm extends FormBase {
  public function getFormId() {
    return 'participant_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, Participant $participant = null, PollInterface $poll = null) {
    $form_state->set('participant', $participant);
    $form_state->set('poll', $poll);

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
      '#default_value' => $participant->get('first_name')->value,
    ];

    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
      '#default_value' => $participant->get('last_name')->value,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => $participant->get('email')->value,
    ];


    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Participant'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
      ],
    ];

    //$form['#attached']['library'][] = 'poll/invite';
    $form['#attached']['library'][] = 'poll/poll_participant';
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $participant = $form_state->get('participant');
    $values = $form_state->getValues();

    $participant->set('first_name', $values['first_name']);
    $participant->set('last_name', $values['last_name']);
    $participant->set('email', $values['email']);
    $participant->save();
  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      return $response->addCommand(new ReplaceCommand('#participant-edit-form', $form));
    } else {
      $poll = $form_state->get('poll');
      $url = Url::fromRoute('entity.poll.participants', ['poll' => $poll->id()]);
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand($url->toString()));
    }

    return $response;
  }
}
