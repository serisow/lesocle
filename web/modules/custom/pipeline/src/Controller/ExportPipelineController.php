<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\pipeline\Entity\PipelineInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class ExportPipelineController extends ControllerBase {
  public function exportPipeline(PipelineInterface $pipeline) {
    $config = $pipeline->toArray();

    // Get step types
    $step_types = $pipeline->getStepTypes();

    // Clear existing step types and add them back in sorted order
    $sorted_step_types = [];
    foreach ($step_types as $uuid => $step_type) {
      $sorted_step_types[$uuid] = [
        'id' => $step_type->getPluginId(),
        'data' => $step_type->getConfiguration(),
        'weight' => $step_type->getWeight(),
        'uuid' => $uuid,
      ];
    }

    // Sort the step types by weight
    uasort($sorted_step_types, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    $config['step_types'] = $sorted_step_types;

    $yaml = Yaml::dump($config, 4, 2);
    $response = new Response($yaml);
    $response->headers->set('Content-Type', 'text/yaml');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $pipeline->id() . '.yml"');
    return $response;
  }
}
