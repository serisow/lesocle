<?php

namespace Drupal\pipeline\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirmation form for deleting an Action Config entity.
 */
class ActionConfigDeleteForm extends EntityConfirmFormBase
{

  /**
   * {@inheritdoc}
   */
  public function getQuestion()
  {
    // Display the confirmation message with the entity label.
    return $this->t('Are you sure you want to delete the Action Config %label?', ['%label' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl()
  {
    // Redirect to the collection page when the delete is canceled.
    return new Url('entity.action_config.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText()
  {
    // Label for the confirmation button.
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->entity->delete();

    // Display a confirmation message.
    $this->messenger()->addMessage($this->t('The Action Config %label has been deleted.', ['%label' => $this->entity->label()]));

    // Redirect to the list of LLM Config entities after deletion.
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}

