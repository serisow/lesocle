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

    if ($step_type instanceof StepTypeExecutableInterface) {
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
        $result = $step_type->execute($context);

        $context['message'] = t('Processed step: @step @info', [
          '@step' => $step_type->getStepDescription(),
          '@info' => $step_info,
        ]);

        // Save the updated pipeline
        $pipeline->save();
      } catch (\Exception $e) {
        $context['error_message'] = $e->getMessage();
      }
    } else {
      $context['error_message'] = t('Step type does not implement StepTypeExecutableInterface');
    }
  }
  public static function finishBatch($success, $results, $operations) {
    if ($success) {
      $message = t('Pipeline executed successfully.');
      foreach ($results as $step => $result) {
        \Drupal::messenger()->addMessage(t('Step @index result: Success!', ['@index' => $step]));
      }
    } else {
      $message = t('Pipeline execution failed.');
    }

    \Drupal::messenger()->addMessage($message);
  }
}
