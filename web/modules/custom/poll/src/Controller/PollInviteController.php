<?php
namespace Drupal\poll\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\poll\Entity\PollInterface;
use Drupal\participant\Entity\Participant;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManagerInterface;

class PollInviteController extends ControllerBase {
  protected $mailManager;
  public function __construct(MailManagerInterface $mail_manager) {
    $this->mailManager = $mail_manager;
  }
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail')
    );
  }
  public function sendInvite(PollInterface $poll, Participant $participant) {
    $to = $participant->getEmail();
    $params = [
      'subject' => $this->t('Invitation to take the poll: @poll_name', ['@poll_name' => $poll->label()]),
      'poll_name' => $poll->label(),
      'participant_name' => $participant->getFirstName(),
      'poll_link' => $participant->getFrontendPollUrl(),
    ];
    $result = $this->mailManager->mail('poll', 'invite', $to, $this->languageManager()->getDefaultLanguage()->getId(), $params);
    $response = new AjaxResponse();
    if ($result['result']) {
      $response->addCommand(new MessageCommand($this->t('Invitation sent successfully to @email.', ['@email' => $to]), null, ['type' => 'status']));
    } else {
      $response->addCommand(new MessageCommand($this->t('There was a problem sending the invitation to @email.', ['@email' => $to]), null, ['type' => 'error']));
    }

    return $response;
  }
}
