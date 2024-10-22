<?php
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
