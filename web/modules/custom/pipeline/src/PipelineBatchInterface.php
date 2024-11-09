<?php
/**
 * Defines the interface for pipeline batch processing.
 *
 * Provides contract requirements for pipeline execution through Drupal's Batch API.
 * This interface ensures consistent handling of step-by-step pipeline execution,
 * result tracking, and error management. It specifically handles the execution
 * flow for pipelines triggered directly through Drupal's UI or manual execution.
 *
 * Contract requirements:
 * - Must handle individual step execution
 * - Must maintain execution context
 * - Must track PipelineRun entity state
 * - Must capture execution errors
 * - Must manage step results
 *
 * Batch patterns:
 * - Step-by-step execution tracking
 * - Error capture and logging
 * - PipelineRun entity updates
 * - Step result aggregation
 * - Execution status management
 *
 * Key relationships:
 * - Used by Pipeline entity for execution
 * - Works with PipelineRun for result storage
 * - Integrates with PipelineErrorHandler
 * - Manages StepType execution
 * - Updates execution status
 *
 * Implementation context:
 * - Used for UI-triggered executions
 * - Handles manual pipeline runs
 * - Manages immediate execution flow
 * - Different from Go service execution
 * - Supports Drupal's Batch API
 *
 * @see \Drupal\pipeline\PipelineBatch
 * @see \Drupal\pipeline\Entity\PipelineRun
 * @see \Drupal\pipeline\Service\PipelineErrorHandler
 */
namespace Drupal\pipeline;

interface PipelineBatchInterface
{

  /**
   * Process a single step of the pipeline.
   *
   * Executes an individual step within the pipeline, managing its state,
   * capturing results, and handling any errors. Updates the PipelineRun
   * entity with step execution results.
   *
   * @param string $pipeline_id
   *   The ID of the pipeline being executed.
   * @param string $step_uuid
   *   The UUID of the step to process.
   * @param array $context
   *   The batch context containing execution state and results.
   */
  public function processStep($pipeline_id, $step_uuid, array &$context);

  /**
   * Finish the batch process.
   *
   * Completes the pipeline execution, updates final status, handles any
   * cleanup required, and manages the PipelineRun entity state completion.
   *
   * @param bool $success
   *   Indicates whether the batch process was successful.
   * @param array $results
   *   The accumulated results from all processed steps.
   * @param array $operations
   *   The operations that were processed in this batch.
   */
  public function finishBatch($success, $results, $operations);
}
