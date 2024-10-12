<?php
namespace Drupal\pipeline_run\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\pipeline_run\Entity\PipelineRun;

class PipelineRunStepsController extends ControllerBase {
  public function viewSteps(PipelineRun $pipeline_run) {
    $step_results = json_decode($pipeline_run->getStepResults(), TRUE);

    // Sort steps by sequence
    uasort($step_results, function($a, $b) {
      return $a['sequence'] <=> $b['sequence'];
    });

    $build = [
      '#theme' => 'pipeline_run_steps',
      '#pipeline_run' => $pipeline_run,
      '#steps' => $step_results,
    ];

    return $build;
  }

}
