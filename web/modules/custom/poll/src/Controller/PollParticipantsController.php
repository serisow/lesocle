<?php
namespace Drupal\poll\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\poll\Entity\PollInterface;

class PollParticipantsController extends ControllerBase
{

  public function content(PollInterface $poll)
  {
    // Add New Participant button
    $add_participant_button = [
      '#type' => 'link',
      '#title' => $this->t('Add New Participant'),
      '#url' => Url::fromRoute('poll.add_participant_modal', ['poll' => $poll->id()]),
      '#attributes' => [
        'class' => ['use-ajax', 'button', 'button--primary'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => json_encode([
          'width' => 'auto',
          'dialogClass' => 'participant-add-modal',
        ]),
      ],
    ];

    // Add a list of existing participants
    $existing_participants = $this->entityTypeManager()
      ->getStorage('participant')
      ->loadByProperties(['poll' => $poll->id()]);

    $existing_participants_render = [
      '#theme' => 'table',
      '#header' => ['First Name', 'Last Name', 'Email', 'Status', 'Invite', 'Operations'],
      '#rows' => [],
      '#empty' => $this->t('No participants found for this poll.'),
    ];

    foreach ($existing_participants as $participant) {
      // Generate the frontend url.
      $invite_url = Url::fromRoute('poll.send_invite', [
        'poll' => $poll->id(),
        'participant' => $participant->id(),
      ]);

      $invite_link = Link::fromTextAndUrl($this->t('Send Invite'), $invite_url)->toRenderable();
      $invite_link['#attributes']['class'][] = 'use-ajax';
      $invite_link['#attributes']['class'][] = 'button';
      $invite_link['#attributes']['class'][] = 'button--small';
      $invite_link['#attributes']['class'][] = 'invite-button';
      $invite_link['#attributes']['data-participant-id'] = $participant->id();

      // Check if the participant has completed the poll
      $isCompleted = $participant->getStatus() === 'completed';

      if ($isCompleted) {
        $invite_link['#attributes']['disabled'] = 'disabled';
        $invite_link['#attributes']['class'][] = 'is-disabled';
        $invite_link['#attributes']['title'] = $this->t('Invite cannot be sent for completed polls');
      }

      $existing_participants_render['#rows'][] = [
        $participant->getFirstName(),
        $participant->getLastName(),
        $participant->getEmail(),
        $participant->getStatus(),
        ['data' => $invite_link],
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('participant.edit_modal', [
                  'poll' => $poll->id(),
                  'participant' => $participant->id(),
                ]),
                'attributes' => [
                  'class' => ['use-ajax'],
                  'data-dialog-type' => 'modal',
                  'data-dialog-options' => json_encode([
                    'width' => 'auto',
                    'dialogClass' => 'participant-edit-modal',
                  ]),
                ],
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => $participant->toUrl('delete-form'),
              ],
            ],
          ],
        ],
      ];
    }

    return [
      '#attached' => [
        'library' => [
          'poll/poll_participant',
          'poll/participant_styles',
        ],
      ],
      'add_participant' => $add_participant_button,
      'existing_participants' => $existing_participants_render,
    ];
  }

}
