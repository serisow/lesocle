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

    // Load the pipeline entity
    $pipeline = \Drupal::entityTypeManager()->getStorage('pipeline')->load($pipeline_run->getPipelineId());

    // Enhance step data with additional information
    foreach ($step_results as $uuid => &$step) {
      $step_type = $pipeline->getStepType($uuid);
      $config = $step_type->getConfiguration();

      switch ($step['step_type']) {
        case 'llm_step':
          $llm_config_id = $config['data']['llm_config'] ?? '';
          $llm_config = \Drupal::entityTypeManager()->getStorage('llm_config')->load($llm_config_id);
          $step['additional_info'] = $llm_config ? $llm_config->getModelName() : 'N/A';
          break;
        case 'action_step':
          $step['additional_info'] = $config['data']['action_config'] ?? 'N/A';
          break;
        case 'google_search':
          $step['additional_info'] = 'N/A';
          break;
        default:
          $step['additional_info'] = 'N/A';
      }
    }

    $build = [
      '#theme' => 'pipeline_run_steps',
      '#pipeline_run' => $pipeline_run,
      '#steps' => $step_results,
    ];

    return $build;
  }

}
