<?php
namespace Drupal\pipeline;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pipeline\Plugin\StepType\ActionStep;
use Drupal\pipeline\Plugin\StepType\LLMStep;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;

class PipelineBatch {
  use StringTranslationTrait;
  public static function processStep($pipeline_id, $step_uuid, &$context) {
    $pipeline = \Drupal::entityTypeManager()->getStorage('pipeline')->load($pipeline_id);
    $step_type = $pipeline->getStepType($step_uuid);

    if (!isset($context['memory'])) {
      $context['memory'] = [];
    }

    if ($step_type instanceof StepTypeExecutableInterface) {
      try {
        // Get the LLM Config associated with this step
        $config = $step_type->getConfiguration();
        $step_info = '';
        $context['memory'][$step_uuid] = $step_type->getStepOutputKey();

        if ($step_type instanceof LLMStep) {
          $llm_config_id = $config['data']['llm_config'] ?? '';
          $llm_config = \Drupal::entityTypeManager()->getStorage('llm_config')->load($llm_config_id);
          $model_name = $llm_config ? $llm_config->getModelName() : 'N/A';
          $step_info = t('(Model: @model)', ['@model' => $model_name]);
        } elseif ($step_type instanceof ActionStep) {
          $action_config_id = $config['data']['action_config'] ?? '';
          $action_config = \Drupal::entityTypeManager()->getStorage('action_config')->load($action_config_id);
          $action_type = $action_config ? $action_config->getActionType() : 'N/A';
          $step_info = t('(Action: @action)', ['@action' => $action_type]);
        }

        $result = $step_type->execute($context);
        $context['results'][] = $result;


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
      foreach ($results as $index => $result) {
        \Drupal::messenger()->addMessage(t('Step @index result: @result', ['@index' => $index + 1, '@result' => $result]));
      }
    } else {
      $message = t('Pipeline execution failed.');
    }

    \Drupal::messenger()->addMessage($message);
  }
}
