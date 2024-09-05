<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\pipeline\Entity\PipelineInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class ExportPipelineController extends ControllerBase {
  public function exportPipeline(PipelineInterface $pipeline) {
    $config = $pipeline->toArray();
    $yaml = Yaml::dump($config, 4, 2);
    $response = new Response($yaml);
    $response->headers->set('Content-Type', 'text/yaml');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $pipeline->id() . '.yml"');
    return $response;
  }
}
