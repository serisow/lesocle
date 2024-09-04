<?php
namespace Drupal\participant\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

class ParticipantForm extends ContentEntityForm {

  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $entity = $this->getEntity();

    $message_arguments = ['%label' => $entity->label()];
    $logger_arguments = $message_arguments + ['link' => $entity->toLink($this->t('View'))->toString()];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New participant %label has been created.', $message_arguments));
        $this->logger('participant')->notice('Created new participant %label', $logger_arguments);
        break;

      default:
        $this->messenger()->addStatus($this->t('The participant %label has been updated.', $message_arguments));
        $this->logger('participant')->notice('Updated participant %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.participant.canonical', ['participant' => $entity->id()]);

    return $result;
  }

}
