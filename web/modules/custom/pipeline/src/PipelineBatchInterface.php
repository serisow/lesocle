<?php
namespace Drupal\pipeline;

interface PipelineBatchInterface
{

  /**
   * Process a single step of the pipeline.
   *
   * @param string $pipeline_id
   *   The ID of the pipeline.
   * @param string $step_uuid
   *   The UUID of the step to process.
   * @param array $context
   *   The batch context.
   */
  public function processStep($pipeline_id, $step_uuid, array &$context);

  /**
   * Finish the batch process.
   *
   * @param bool $success
   *   Indicates whether the batch process was successful.
   * @param array $results
   *   The results of the batch process.
   * @param array $operations
   *   The operations that were processed.
   */
  public function finishBatch($success, $results, $operations);
}
