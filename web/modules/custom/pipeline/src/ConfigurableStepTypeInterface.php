<?php
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
