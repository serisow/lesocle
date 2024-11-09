<?php
/**
 * Defines executable behavior for Step Type plugins.
 *
 * This interface extends StepType functionality specifically for steps that can
 * be executed directly within a pipeline. It adds execution capability with
 * context awareness and PipelineContext integration.
 *
 * Additional requirements beyond StepTypeInterface:
 * - Must implement step execution logic
 * - Must handle pipeline context
 * - Must manage execution results
 * - Must support context data passing
 *
 * Execution patterns:
 * - Executes step logic with access to batch context
 * - Manages step output in pipeline context
 * - Handles step-specific error states
 * - Processes previous step results
 *
 * Implementation notes:
 * - Used by LLMStep for model interactions
 * - Used by ActionStep for action execution
 * - Used by GoogleSearchStep for search operations
 * - Used by DocumentSearchStep for RAG operations
 *
 * @see \Drupal\pipeline\Plugin\StepTypeInterface
 * @see \Drupal\pipeline\PipelineContext
 * @see \Drupal\pipeline\AbstractLLMStepType
 */
namespace Drupal\pipeline\Plugin;

interface StepTypeExecutableInterface extends StepTypeInterface {
  /**
   * Executes the step type logic.
   *
   * @param array $context
   *   The batch context array.
   *
   * @return string
   *   The result of the step execution.
   */
  public function execute(array &$context): string;
}
