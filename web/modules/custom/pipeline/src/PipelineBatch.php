<?php
namespace Drupal\pipeline;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pipeline\Plugin\StepType\ActionStep;
use Drupal\pipeline\Plugin\StepType\GoogleSearchStep;
use Drupal\pipeline\Plugin\StepType\LLMStep;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;

class PipelineBatch {
  use StringTranslationTrait;
  public static function processStep($pipeline_id, $step_uuid, &$context) {
    $pipeline = \Drupal::entityTypeManager()->getStorage('pipeline')->load($pipeline_id);
    $step_type = $pipeline->getStepType($step_uuid);
    $pipeline_run_id = \Drupal::state()->get('pipeline.current_run_id');
    $pipeline_run = \Drupal::entityTypeManager()->getStorage('pipeline_run')->load($pipeline_run_id);

    if ($step_type instanceof StepTypeExecutableInterface) {
      $step_result = [
        'step_uuid' => $step_uuid,
        'step_description' => $step_type->getStepDescription(),
        'status' => 'running',
        'start_time' => \Drupal::time()->getCurrentTime(),
        'step_type' => $step_type->getPluginId(),
        'sequence' => $step_type->getWeight(),
      ];

      $start_time = microtime(true);
      try {
        $config = $step_type->getConfiguration();
        $step_info = self::getStepInfo($step_type, $config);

        $result = $step_type->execute($context);
        $step_result['status'] = 'completed';
        $step_result['data'] = $result;

        $context['message'] = t('Processed step: @step @info', [
          '@step' => $step_type->getStepDescription(),
          '@info' => $step_info,
        ]);

      } catch (\Exception $e) {
        $step_result['status'] = 'failed';
        $step_result['error_message'] = $e->getMessage();
        $context['error_message'] = $e->getMessage();
        $context['message'] = t('Failed step: @step @info', [
          '@step' => $step_type->getStepDescription(),
          '@info' => $step_info,
        ]);
      }

      $step_result['end_time'] = \Drupal::time()->getCurrentTime();
      $step_result['duration'] = $step_result['end_time'] - $step_result['start_time'];


      // Update step_results field
      $step_results = json_decode($pipeline_run->get('step_results')->value, TRUE) ?: [];
      $step_results[$step_uuid] = $step_result;
      $pipeline_run->set('step_results', json_encode($step_results));

      // Update PipelineRun status
      if (isset($context['error_message'])) {
        $pipeline_run->set('status', 'failed');
        $pipeline_run->set('error_message', $context['error_message']);
      } else {
        $pipeline_run->set('status', 'running'); // Will be set to 'completed' in finishBatch if all steps succeed
      }
      $pipeline_run->save();

    } else {
      $context['error_message'] = t('Step type does not implement StepTypeExecutableInterface');
      $pipeline_run->set('status', 'failed');
      $pipeline_run->set('error_message', $context['error_message']);
      $pipeline_run->save();
    }

  }
  private static function getStepInfo($step_type, $config)
  {
    switch (true) {
      case $step_type instanceof LLMStep:
        $llm_config_id = $config['data']['llm_config'] ?? '';
        $llm_config = \Drupal::entityTypeManager()->getStorage('llm_config')->load($llm_config_id);
        $model_name = $llm_config ? $llm_config->getModelName() : 'N/A';
        return t('(Model: @model)', ['@model' => $model_name]);

      case $step_type instanceof ActionStep:
        $action_config_id = $config['data']['action_config'] ?? '';
        $action_config = \Drupal::entityTypeManager()->getStorage('action_config')->load($action_config_id);
        $action_service = $action_config ? $action_config->getActionService() : 'N/A';
        return t('(Action: @action)', ['@action' => $action_service]);

      case $step_type instanceof GoogleSearchStep:
        $query = $config['data']['query'] ?? 'N/A';
        $category = $config['data']['category'] ?? '';
        return t('(Query: @query, Category: @category)', [
          '@query' => $query,
          '@category' => $category ?: 'N/A',
        ]);

      default:
        return '';
    }
  }

  public static function finishBatch($success, $results, $operations) {
    \Drupal::logger('pipeline')->notice('finishBatch method called. Success: @success', ['@success' => $success ? 'true' : 'false']);
    $pipeline_run_id = \Drupal::state()->get('pipeline.current_run_id');
    if (!$pipeline_run_id) {
      \Drupal::logger('pipeline')->error('Pipeline run ID not found in state.');
      return;
    }

    $pipeline_run = \Drupal::entityTypeManager()->getStorage('pipeline_run')->load($pipeline_run_id);
    if (!$pipeline_run) {
      \Drupal::logger('pipeline')->error('Pipeline run with ID @id not found.', ['@id' => $pipeline_run_id]);
      return;
    }

    if ($success) {
      $pipeline_run->set('status', 'completed');
      $message = t('Pipeline executed successfully.');
    } else {
      $pipeline_run->set('status', 'failed');
      $message = t('Pipeline execution failed.');
    }

    $pipeline_run->set('end_time', \Drupal::time()->getCurrentTime());

    $pipeline_run->save();

    // Clear the state after we're done
    \Drupal::state()->delete('pipeline.current_run_id');

    \Drupal::messenger()->addMessage($message);
  }


}
