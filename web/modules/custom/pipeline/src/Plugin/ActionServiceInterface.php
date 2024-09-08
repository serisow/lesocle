<?php
namespace Drupal\pipeline\Plugin;

interface ActionServiceInterface
{
  /**
   * Executes the action.
   *
   * @param array $config
   *   The action configuration.
   * @param array $context
   *   The context data.
   *
   * @return string
   *   The result of the action execution.
   */
  public function executeAction(array $config, array &$context): string;
}
