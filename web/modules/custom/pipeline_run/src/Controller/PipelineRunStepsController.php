<?php
namespace Drupal\pipeline_run\Controller;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\pipeline_run\Entity\PipelineRun;

class PipelineRunStepsController extends ControllerBase {
  public function viewSteps(PipelineRun $pipeline_run) {
    $step_results = json_decode($pipeline_run->getStepResults(), TRUE);

    if (!is_array($step_results)) {
      $this->messenger()->addError($this->t('Unable to process pipeline run steps. Invalid data format.'));
      return [];
    }

    // Sort steps by sequence in ascending order
    uasort($step_results, function($a, $b) {
      return $a['sequence'] <=> $b['sequence'];
    });

    // Load the pipeline entity
    $pipeline = \Drupal::entityTypeManager()->getStorage('pipeline')->load($pipeline_run->getPipelineId());

    // Process step data
    foreach ($step_results as $uuid => &$step) {
      try {
        $step_type = $pipeline->getStepType($uuid);
        $config = $step_type ? $step_type->getConfiguration() : [];

        // Ensure all necessary keys exist
        $step += [
          'step_description' => 'N/A',
          'step_type' => 'N/A',
          'status' => 'N/A',
          'duration' => 0,
          'data' => '',
          'sequence' => 0,
        ];

        // Process additional info
        switch ($step['step_type']) {
          case 'llm_step':
            $llm_config_id = $config['data']['llm_config'] ?? '';
            $llm_config = \Drupal::entityTypeManager()->getStorage('llm_config')->load($llm_config_id);
            $step['additional_info'] = $llm_config ? $llm_config->getModelName() : 'N/A';
            break;
          case 'action_step':
            $step['additional_info'] = $config['data']['action_config'] ?? 'N/A';
            break;
          default:
            $step['additional_info'] = 'N/A';
        }

        // Process the data field
        if (is_string($step['data'])) {
          $decoded = json_decode($step['data'], TRUE);
          if (json_last_error() === JSON_ERROR_NONE) {
            // If successfully decoded, re-encode with pretty print
            $step['data'] = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
          }
          // If not valid JSON, leave as is
        } elseif (is_array($step['data']) || is_object($step['data'])) {
          // If already an array or object, encode with pretty print
          $step['data'] = json_encode($step['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        // For other data types, convert to string
        if (!is_string($step['data'])) {
          $step['data'] = var_export($step['data'], true);
        }

      } catch (PluginNotFoundException $e) {
        $step['step_type'] = 'Removed Step';
        $step['additional_info'] = $this->t('This step has been removed from the pipeline configuration.');
        $step['status'] = 'N/A';
        $this->messenger()->addWarning($this->t('One or more steps in this pipeline run are no longer present in the current pipeline configuration. The results for these steps are still displayed but may not reflect the current pipeline structure.'));
      }
    }

    // Add a link to the pipeline edit form
    $pipeline_edit_url = Url::fromRoute('entity.pipeline.edit_form', ['pipeline' => $pipeline_run->getPipelineId()])->toString();
    $pipeline_edit_link = $this->t('<a href="@url">Edit Pipeline</a>', ['@url' => $pipeline_edit_url]);

    return [
      '#theme' => 'pipeline_run_steps',
      '#pipeline_run' => $pipeline_run,
      '#steps' => $step_results,
      '#pipeline_edit_link' => $pipeline_edit_link,
    ];
  }
}
