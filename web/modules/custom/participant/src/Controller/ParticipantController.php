<?php
namespace Drupal\participant\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\poll\Entity\PollInterface;
use Drupal\participant\Entity\Participant;

class ParticipantController extends ControllerBase {
  public function openEditModal(PollInterface $poll, Participant $participant) {
    $form = $this->formBuilder()->getForm('Drupal\participant\Form\ParticipantEditForm', $participant, $poll);

    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(
      $this->t('Edit Participant'),
      $form,
      ['width' => 'auto', 'dialogClass' => 'participant-edit-modal']
    ));

    return $response;
  }

  public function openAddModal(PollInterface $poll) {
    $form = $this->formBuilder()->getForm('Drupal\participant\Form\ParticipantBulkAddForm', $poll->id());

    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(
      $this->t('Add New Participant'),
      $form,
      ['width' => 'auto', 'dialogClass' => 'participant-add-modal']
    ));

    return $response;
  }

}
