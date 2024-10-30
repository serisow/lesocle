<?php
/**
 * Handles pipeline execution through Drupal's batch API.
 *
 * This service class manages the Drupal-side execution of pipeline steps using
 * the Batch API. It is specifically responsible for handling pipelines that
 * are executed directly within Drupal, particularly for manual/immediate executions
 * triggered from the UI.
 *
 * Critical functionalities:
 * - Processes steps sequentially through Drupal's batch operations
 * - Manages execution context and results between steps
 * - Creates and updates PipelineRun entities
 * - Handles error capturing and logging
 *
 * Error handling:
 * - Captures PHP errors during step execution
 * - Updates pipeline failure counts
 * - Creates detailed log files for failed executions
 * - Manages step-specific error states
 *
 * Key relationships:
 * - Works with PipelineRun entities for result storage
 * - Uses PipelineErrorHandler for error logging
 * - Integrates with Drupal's Batch API system
 *
 * Note: Scheduled pipeline executions and Go service interactions are handled
 * separately through the Go pipeline service and its API endpoints.
 *
 * @see \Drupal\pipeline\Service\PipelineErrorHandler
 * @see \Drupal\pipeline\Entity\PipelineRun
 * @see \Drupal\pipeline\Controller\PipelineExecutionController
 */

namespace Drupal\pipeline;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\pipeline\Plugin\StepType\ActionStep;
use Drupal\pipeline\Plugin\StepType\GoogleSearchStep;
use Drupal\pipeline\Plugin\StepType\LLMStep;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Drupal\pipeline\Service\PipelineErrorHandler;

class PipelineBatch {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Core\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The pipeline error handler.
   *
   * @var \Drupal\pipeline\Service\PipelineErrorHandler
   */
  protected $errorHandler;

  /**
   * Constructs a new PipelineBatch object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\pipeline\Service\PipelineErrorHandler $error_handler
   *   The pipeline error handler service.
 */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    TranslationInterface $string_translation,
    StateInterface $state,
    TimeInterface $time,
    LoggerChannelFactoryInterface $logger_factory,
    PipelineErrorHandler $error_handler
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->setStringTranslation($string_translation);
    $this->state = $state;
    $this->time = $time;
    $this->loggerFactory = $logger_factory;
    $this->errorHandler = $error_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function processStep($pipeline_id, $step_uuid, &$context) {
    $pipeline = $this->entityTypeManager->getStorage('pipeline')->load($pipeline_id);
    $step_type = $pipeline->getStepType($step_uuid);
    $pipeline_run_id = $this->state->get('pipeline.current_run_id');
    $pipeline_run = $this->entityTypeManager->getStorage('pipeline_run')->load($pipeline_run_id);

    if ($step_type instanceof StepTypeExecutableInterface) {
      $step_result = [
        'step_uuid' => $step_uuid,
        'step_description' => $step_type->getStepDescription(),
        'status' => 'running',
        'start_time' => $this->time->getCurrentTime(),
        'step_type' => $step_type->getPluginId(),
        'sequence' => $step_type->getWeight(),
      ];

      // Start error capture for this step
     $error_collector = $this->errorHandler->startErrorCapture($step_uuid);

      try {
        $config = $step_type->getConfiguration();
        $step_info = self::getStepInfo($step_type, $config);

        $result = $step_type->execute($context);
        $step_result['status'] = 'completed';
        $step_result['data'] = $result;

        $context['message'] = $this->t('Processed step: @step @info', [
          '@step' => $step_type->getStepDescription(),
          '@info' => $step_info,
        ]);

      } catch (\Exception $e) {
        $step_result['status'] = 'failed';
        $step_result['error_message'] = $e->getMessage();
        $context['error_message'] = $e->getMessage();
        $context['message'] = $this->t('Failed step: @step @info', [
          '@step' => $step_type->getStepDescription(),
          '@info' => $step_info,
        ]);
      } finally {
        // Stop error capture and collect any PHP errors
        $this->errorHandler->stopErrorCapture();
        if (!empty($error_collector[$step_uuid])) {
          $step_result['php_errors'] = $error_collector[$step_uuid];
        }
      }

      $step_result['end_time'] = $this->time->getCurrentTime();
      $step_result['duration'] = $step_result['end_time'] - $step_result['start_time'];


      // Update step_results field
      $step_results = json_decode($pipeline_run->get('step_results')->value, TRUE) ?: [];
      $step_results[$step_uuid] = $step_result;
      $pipeline_run->set('step_results', json_encode($step_results));

      // Create log file if there are errors
     if ($step_result['status'] === 'failed' || !empty($step_result['php_errors'])) {
        if ($log_file = $this->errorHandler->createLogFile($step_results, $pipeline_run_id)) {
          $pipeline_run->setLogFile($log_file->id());
        }
      }

      // Update PipelineRun status
      if (isset($context['error_message'])) {
        $pipeline_run->set('status', 'failed');
        $pipeline_run->set('error_message', $context['error_message']);
      } else {
        $pipeline_run->set('status', 'running'); // Will be set to 'completed' in finishBatch if all steps succeed
      }
      $pipeline_run->save();

    } else {
      $error_message = $this->t('Step type does not implement StepTypeExecutableInterface');
      $context['error_message'] = $error_message;
      $context['message'] = $this->t('Failed step: @error', ['@error' => $error_message]); // Added line
      $pipeline_run->set('status', 'failed');
      $pipeline_run->set('error_message', $context['error_message']);
      $pipeline_run->save();
    }

  }
  private function getStepInfo($step_type, $config)
  {
    switch (true) {
      case $step_type instanceof LLMStep:
        $llm_config_id = $config['data']['llm_config'] ?? '';
        $llm_config = $this->entityTypeManager->getStorage('llm_config')->load($llm_config_id);
        $model_name = $llm_config ? $llm_config->getModelName() : 'N/A';
        return $this->t('(Model: @model)', ['@model' => $model_name]);

      case $step_type instanceof ActionStep:
        $action_config_id = $config['data']['action_config'] ?? '';
        $action_config = $this->entityTypeManager->getStorage('action_config')->load($action_config_id);
        $action_service = $action_config ? $action_config->getActionService() : 'N/A';
        return $this->t('(Action: @action)', ['@action' => $action_service]);

      case $step_type instanceof GoogleSearchStep:
        $query = $config['data']['query'] ?? 'N/A';
        $category = $config['data']['category'] ?? '';
        return $this->t('(Query: @query, Category: @category)', [
          '@query' => $query,
          '@category' => $category ?: 'N/A',
        ]);

      default:
        return '';
    }
  }

  public function finishBatch($success, $results, $operations) {
    $this->loggerFactory->get('pipeline')->notice('finishBatch method called. Success: @success', ['@success' => $success ? 'true' : 'false']);
    $pipeline_run_id = $this->state->get('pipeline.current_run_id');
    if (!$pipeline_run_id) {
      $this->loggerFactory->get('pipeline')->error('Pipeline run ID not found in state.');
      return;
    }

    $pipeline_run = $this->entityTypeManager->getStorage('pipeline_run')->load($pipeline_run_id);
    if (!$pipeline_run) {
      $this->loggerFactory->get('pipeline')->error('Pipeline run with ID @id not found.', ['@id' => $pipeline_run_id]);
      return;
    }
    if ($success) {
      $pipeline_run->set('status', 'completed');
      $message = $this->t('Pipeline executed successfully.');
    } else {
      $pipeline_run->set('status', 'failed');
      $message = $this->t('Pipeline execution failed.');
    }
    $pipeline_run->set('end_time', $this->time->getCurrentTime());
    $pipeline_run->save();
    // Clear the state after we're done
    $this->state->delete('pipeline.current_run_id');
    $this->messenger->addMessage($message);
  }


}
