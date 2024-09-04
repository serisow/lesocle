<?php
namespace Drupal\poll\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Controller for poll addition forms.
 */
class PollAddForm extends PollFormBase {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->messenger()->addMessage($this->t('Poll %name was created.', ['%name' => $this->entity->label()]));
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Create new poll');
    return $actions;
  }

}
