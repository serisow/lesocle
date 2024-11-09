<?php
/**
 * Defines the interface for configurable step types.
 *
 * Extends base StepType functionality to support configuration forms and
 * configuration management. This interface is specifically for steps that
 * require user-configurable settings.
 *
 * Additional requirements beyond StepTypeInterface:
 * - Must provide configuration form
 * - Must handle configuration validation
 * - Must process form submissions
 * - Must manage configuration storage
 *
 * Configuration patterns:
 * - Implements form API integration
 * - Handles configuration defaults
 * - Processes configuration validation
 * - Manages configuration updates
 * - Supports AJAX form updates
 *
 * Implementation examples:
 * - LLMStep for model and prompt configuration
 * - ActionStep for action settings
 * - SearchStep for search parameters
 * - DocumentSearchStep for RAG settings
 *
 * @see \Drupal\pipeline\Plugin\StepTypeInterface
 * @see \Drupal\pipeline\ConfigurableStepTypeBase
 * @see \Drupal\Core\Plugin\PluginFormInterface
 */

namespace Drupal\pipeline;

use Drupal\pipeline\Plugin\StepTypeInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the interface for configurable step type.
 *
 * @see \Drupal\pipeline\Plugin\StepType\Annotation\StepType
 * @see \Drupal\pipeline\ConfigurableStepTypeBase
 * @see \Drupal\pipeline\ConfigurableStepTypeInterface
 * @see \Drupal\pipeline\Plugin\StepTypeInterface
 * @see \Drupal\pipeline\StepTypeBase
 * @see \Drupal\pipeline\Plugin\StepTypeManager
 * @see plugin_api
 */
interface ConfigurableStepTypeInterface extends StepTypeInterface, PluginFormInterface {}
