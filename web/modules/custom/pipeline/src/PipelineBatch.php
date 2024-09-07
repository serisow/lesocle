<?php
namespace Drupal\pipeline;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;

class PipelineBatch {
  use StringTranslationTrait;

  public static function processStep($pipeline_id, $step_uuid, &$context) {
    $pipeline = \Drupal::entityTypeManager()->getStorage('pipeline')->load($pipeline_id);
    $step_type = $pipeline->getStepType($step_uuid);

    if ($step_type instanceof StepTypeExecutableInterface) {
      try {
        $result = $step_type->execute($context);
        $context['results'][] = $result;
        $context['message'] = t('Processed step: @step', ['@step' => $step_type->getStepDescription()]);

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
