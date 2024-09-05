<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\pipeline\Entity\PipelineInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManagerInterface;

class PipelineInviteController extends ControllerBase {
  protected $mailManager;
  public function __construct(MailManagerInterface $mail_manager) {
    $this->mailManager = $mail_manager;
  }
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail')
    );
  }

}
