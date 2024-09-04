<?php
namespace Drupal\participant\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting a participant.
 */
class ParticipantDeleteForm extends ContentEntityDeleteForm
{
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Store the poll ID in the form state for use in submitForm
    $form_state->set('poll_id', $this->entity->get('poll')->target_id);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->delete();

    $this->messenger()->addStatus($this->t('The participant %label has been deleted.', [
      '%label' => $entity->label(),
    ]));

    $poll_id = $form_state->get('poll_id');
    $form_state->setRedirect('entity.poll.participants', ['poll' => $poll_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.poll.participants', ['poll' => $this->entity->get('poll')->target_id]);
  }
}
