<?php
namespace Drupal\poll\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\poll\Entity\PollInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class ExportPollController extends ControllerBase {
  public function exportPoll(PollInterface $poll) {
    $config = $poll->toArray();
    $yaml = Yaml::dump($config, 4, 2);
    $response = new Response($yaml);
    $response->headers->set('Content-Type', 'text/yaml');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $poll->id() . '.yml"');
    return $response;
  }
}
