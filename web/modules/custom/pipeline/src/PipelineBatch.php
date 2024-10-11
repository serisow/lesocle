<?php
namespace Drupal\pipeline;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pipeline\Plugin\StepType\ActionStep;
use Drupal\pipeline\Plugin\StepType\GoogleSearchStep;
use Drupal\pipeline\Plugin\StepType\LLMStep;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;

class PipelineBatch {
  use StringTranslationTrait;
  public static function processStep($pipeline_id, $step_uuid, $pipeline_run_id, &$context) {
    $pipeline = \Drupal::entityTypeManager()->getStorage('pipeline')->load($pipeline_id);
    $step_type = $pipeline->getStepType($step_uuid);
    $pipeline_run = \Drupal::entityTypeManager()->getStorage('pipeline_run')->load($pipeline_run_id);


    if ($step_type instanceof StepTypeExecutableInterface) {
      // Create PipelineStepRun entity
      $step_run = \Drupal::entityTypeManager()->getStorage('pipeline_step_run')->create([
        'pipeline_run_id' => $pipeline_run_id,
        'step_uuid' => $step_uuid,
        'status' => 'running',
        'start_time' => \Drupal::time()->getCurrentTime(),
        'step_type' => $step_type->getPluginId(),
        'sequence' => $step_type->getWeight(),
      ]);
      $step_run->save();

      try {
        // Get the LLM Config associated with this step
        $config = $step_type->getConfiguration();
        $step_info = '';

        switch (true) {
          case $step_type instanceof LLMStep:
            $llm_config_id = $config['data']['llm_config'] ?? '';
            $llm_config = \Drupal::entityTypeManager()->getStorage('llm_config')->load($llm_config_id);
            $model_name = $llm_config ? $llm_config->getModelName() : 'N/A';
            $step_info = t('(Model: @model)', ['@model' => $model_name]);
            break;

          case $step_type instanceof ActionStep:
            $action_config_id = $config['data']['action_config'] ?? '';
            $action_config = \Drupal::entityTypeManager()->getStorage('action_config')->load($action_config_id);
            $action_service = $action_config ? $action_config->getActionService() : 'N/A';
            $step_info = t('(Action: @action)', ['@action' => $action_service]);
            break;

          case $step_type instanceof GoogleSearchStep:
            $query = $config['data']['query'] ?? 'N/A';
            $category = $config['data']['category'] ?? '';
            $step_info = t('(Query: @query, Category: @category)', [
              '@query' => $query,
              '@category' => $category ?: 'N/A',
            ]);
            break;

          default:
            $step_info = '';
            break;
        }

       // $result = $step_type->execute($context);
        $result = $step_type->execute($context);
        $step_run->set('status', 'success');
        $step_run->set('output', $result);

        $context['message'] = t('Processed step: @step @info', [
          '@step' => $step_type->getStepDescription(),
          '@info' => $step_info,
        ]);

        // Save the updated pipeline
        $pipeline->save();
      } catch (\Exception $e) {
        $step_run->set('status', 'failed');
        $step_run->set('error_message', $e->getMessage());
        $context['error_message'] = $e->getMessage();
        $context['message'] = t('Failed step: @step @info', [
          '@step' => $step_type->getStepDescription(),
          '@info' => $step_info,
        ]);
      }

      $step_run->set('end_time', \Drupal::time()->getCurrentTime());
      $step_run->save();

    } else {
      $context['error_message'] = t('Step type does not implement StepTypeExecutableInterface');
    }

    // Update PipelineRun status
    if (isset($context['error_message'])) {
      $pipeline_run->set('status', 'failed');
      $pipeline_run->set('error_message', $context['error_message']);
    } else {
      $pipeline_run->set('status', 'completed');
    }
    $pipeline_run->save();

  }
  public static function finishBatch($success, $results, $operations, $elapsed) {
    $pipeline_run_id = $operations['context']['pipeline_run_id'] ?? NULL;

    if (!$pipeline_run_id) {
      \Drupal::logger('pipeline')->error('Pipeline run ID not found in batch context.');
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

    \Drupal::messenger()->addMessage($message);
  }
}
