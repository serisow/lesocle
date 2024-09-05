<?php

namespace Drupal\pipeline;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class PipelineBatch {
  use StringTranslationTrait;

  public static function processStep($pipeline_id, $step_uuid, &$context) {
    $pipeline = \Drupal::entityTypeManager()->getStorage('pipeline')->load($pipeline_id);
    $step_type = $pipeline->getStepType($step_uuid);
    $openai_service = \Drupal::service('pipeline.openai_service');

    $config = $step_type->getConfiguration();
    $prompt = $config['data']['prompt'];

    // If there's a previous result, append it to the prompt
    if (!empty($context['results'])) {
      $prompt .= "\n\nPrevious step result: " . end($context['results']);
    }

    try {
      $response = $openai_service->callOpenAI(
        $config['data']['openai_api_url'],
        $config['data']['openai_api_key'],
        $prompt
      );

      // Save the response in the step's configuration
      $config['data']['response'] = $response;
      $step_type->setConfiguration($config);

      // Save the updated pipeline
      $pipeline->save();

      $context['results'][] = $response;
      $context['message'] = t('Processed step: @step', ['@step' => $step_type->getStepDescription()]);
    } catch (\Exception $e) {
      $context['error_message'] = $e->getMessage();
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
