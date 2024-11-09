<?php
/**
 * Defines the interface for Action Service plugins.
 *
 * Action Services handle specific operations that can be executed within a pipeline,
 * such as creating entities, sending notifications, or interacting with external
 * services. They provide a standardized way to execute operations and handle their results.
 *
 * Contract requirements:
 * - Must handle execution location (Drupal/Go)
 * - Must support configuration validation
 * - Must process pipeline context
 * - Must return structured results
 * - Must implement proper error handling
 *
 * Operation patterns:
 * - Must respect execution location settings
 * - Must handle context data from previous steps
 * - Must provide consistent result format
 * - Must support configuration inheritance
 * - Must validate action configurations
 *
 * Common implementations:
 * - WebhookActionService for external APIs
 * - EntityCreationActionService for Drupal entities
 * - DocumentFetchActionService for RAG operations
 * - SMSActionService for notifications
 *
 * @see \Drupal\pipeline\Plugin\ActionService\WebhookActionService
 * @see \Drupal\pipeline\Plugin\ActionService\EntityCreationActionService
 * @see \Drupal\pipeline_drupal_actions\Plugin\ActionService\DocumentFetchActionService
 */

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
