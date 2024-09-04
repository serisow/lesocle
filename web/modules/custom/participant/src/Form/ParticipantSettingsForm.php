<?php
namespace Drupal\participant\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ParticipantSettingsForm extends FormBase
{

  public function getFormId()
  {
    return 'participant_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['settings'] = [
      '#markup' => $this->t('Settings form for Participant entity. Manage field settings here.'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Empty implementation of the abstract submit class.
  }

}
