<?php

/**
 * This controller provides the API interface between Drupal and an external Go service
 * for managing AI/ML pipelines. It is part of the Pipeline module which handles
 * configurable, automated workflows combining Large Language Models (LLMs) with
 * custom actions.
 *
 * The controller enables the Go service to:
 * 1. Retrieve pipeline configurations that are scheduled to run
 * 2. Get detailed pipeline settings including LLM configurations and action steps
 * 3. Manage the execution lifecycle of pipelines
 *
 * Key endpoints exposed:
 * - GET /api/pipelines/scheduled: Fetches pipelines that are due for execution based
 *   on their configured schedule
 * - GET /api/pipelines/{id}: Retrieves full configuration for a specific pipeline
 *
 * The controller handles:
 * - Schedule management (one-time and recurring executions)
 * - Execution validation (status checks, failure handling)
 * - Pipeline configuration retrieval
 * - Security and access control (pending implementation)
 *
 * Each pipeline can contain:
 * - Multiple ordered steps combining LLM calls and actions
 * - Scheduling configuration
 * - Execution constraints and retry logic
 * - Dependencies between steps
 *
 * The controller ensures proper machine-to-machine communication between
 * Drupal (which stores configurations and manages scheduling) and the Go service
 * (which handles actual pipeline execution).
 *
 * @see \Drupal\pipeline\Entity\Pipeline
 * @see \Drupal\pipeline\Controller\PipelineExecutionController
 */

namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\pipeline\Entity\LLMConfig;
use Drupal\pipeline\Plugin\ModelManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\pipeline\StepHandler\StepHandlerManager;

/**
 * Controller for Pipeline API endpoints.
 */
class PipelineApiController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The model manager.
   *
   * @var \Drupal\pipeline\Plugin\ModelManager
   */
  protected $modelManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The step handler manager.
   *
   * @var \Drupal\pipeline\StepHandler\StepHandlerManager
   */
  protected $stepHandlerManager;

  /**
   * Constructs a PipelineApiController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\pipeline\Plugin\ModelManager $model_manager
   *   The model manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\pipeline\StepHandler\StepHandlerManager $step_handler_manager
   *   The step handler manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ModelManager $model_manager,
    LoggerChannelFactoryInterface $logger_factory,
    StepHandlerManager $step_handler_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->modelManager = $model_manager;
    $this->logger = $logger_factory->get('pipeline');
    $this->stepHandlerManager = $step_handler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.model_manager'),
      $container->get('logger.factory'),
      $container->get('pipeline.step_handler.manager')
    );
  }

  /**
   * Returns a list of scheduled pipelines.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing scheduled pipelines.
   */

  public function getScheduledPipelines() {
    $pipeline_storage = $this->entityTypeManager->getStorage('pipeline');
    $pipeline_run_storage = $this->entityTypeManager->getStorage('pipeline_run');

    $current_date = new \DateTime('now', new \DateTimeZone('UTC'));
    $start_of_day = $current_date->setTime(0, 0, 0)->getTimestamp();
    $start_of_next_day = $current_date->modify('+1 day')->getTimestamp();

    // Query for enabled pipelines with any type of schedule
    $query = $pipeline_storage->getQuery()
      ->accessCheck()
      ->condition('status', TRUE)
      // Add condition to filter out pipelines with too many failures
      ->condition('execution_failures', 3, '<')
      ->condition('schedule_type', ['one_time', 'recurring'], 'IN');

    // Add condition for one-time schedules to be within the current day
    $query->condition(
      $query->orConditionGroup()
        ->condition(
          $query->andConditionGroup()
            ->condition('schedule_type', 'one_time')
            ->condition('scheduled_time', $start_of_day, '>=')
            ->condition('scheduled_time', $start_of_next_day, '<')
        )
        ->condition('schedule_type', 'recurring')
    );

    $query->sort('scheduled_time', 'ASC');
    $pipeline_ids = $query->execute();

    // After getting the pipeline IDs...
    if (empty($pipeline_ids)) {
      $this->logger->debug('No eligible pipelines found for scheduling. Query parameters: @params', [
        '@params' => json_encode([
          'start_of_day' => $start_of_day,
          'start_of_next_day' => $start_of_next_day,
          'max_failures' => 3,
        ]),
      ]);
    }

    $scheduled_pipelines = [];
    foreach ($pipeline_ids as $id) {
      /** @var \Drupal\pipeline\Entity\Pipeline $pipeline */
      $pipeline = $pipeline_storage->load($id);

      // Fetch the most recent pipeline run
      $last_run_query = $pipeline_run_storage->getQuery()
        ->accessCheck()
        ->condition('pipeline_id', $pipeline->id())
        ->condition('status', 'completed')
        ->sort('end_time', 'DESC')
        ->range(0, 1);
      $last_run_ids = $last_run_query->execute();

      $last_run_time = 0;
      if (!empty($last_run_ids)) {
        /** @var \Drupal\pipeline_run\Entity\PipelineRun $last_run */
        $last_run = $pipeline_run_storage->load(reset($last_run_ids));
        $last_run_time = (int) $last_run->getEndTime();
      }

      $scheduled_pipeline = [
        'id' => $pipeline->id(),
        'label' => $pipeline->label(),
        'schedule_type' => $pipeline->getScheduleType(),
        'last_run_time' => $last_run_time,
      ];

      switch ($pipeline->getScheduleType()) {
        case 'one_time':
          $scheduled_pipeline['scheduled_time'] = $pipeline->getScheduledTime();
          break;
        case 'recurring':
          $scheduled_pipeline['recurring_frequency'] = $pipeline->getRecurringFrequency();
          $scheduled_pipeline['recurring_time'] = $pipeline->getRecurringTime();
          break;
      }

      $scheduled_pipelines[] = $scheduled_pipeline;
    }

    // Debug: Log query parameters and result
    $this->logger->debug('Scheduled pipelines query executed with parameters: @params', [
      '@params' => json_encode([
        'start_of_day' => $start_of_day,
        'start_of_next_day' => $start_of_next_day,
        'pipeline_ids' => $pipeline_ids,
      ]),
    ]);

    $this->logger->debug('Scheduled pipelines result: @result', [
      '@result' => json_encode($scheduled_pipelines),
    ]);

    return new JsonResponse($scheduled_pipelines);
  }

  /**
   * Returns a specific pipeline by ID.
   *
   * @param string $id
   *   The pipeline ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the pipeline data.
   */
  public function getPipeline($id) {
    /** @var \Drupal\pipeline\Entity\Pipeline $pipeline */
    $pipeline = $this->entityTypeManager->getStorage('pipeline')->load($id);

    if (!$pipeline) {
      return new JsonResponse(['error' => 'Pipeline not found'], 404);
    }

    $pipeline_data = [
      'id' => $pipeline->id(),
      'label' => $pipeline->label(),
      'status' => $pipeline->isEnabled(),
      'instructions' => $pipeline->getInstructions(),
      'created' => $pipeline->getCreatedTime(),
      'changed' => $pipeline->getChangedTime(),
      'schedule_type' => $pipeline->getScheduleType(),
      'execution_failures' => $pipeline->getExecutionFailures(),
      'steps' => [],
    ];

    switch ($pipeline->getScheduleType()) {
      case 'one_time':
        $pipeline_data['scheduled_time'] = $pipeline->getScheduledTime();
        break;
      case 'recurring':
        $pipeline_data['recurring_frequency'] = $pipeline->getRecurringFrequency();
        $pipeline_data['recurring_time'] = $pipeline->getRecurringTime();
        break;
    }

    foreach ($pipeline->getStepTypes() as $step_type) {
      $step_data = [
        'id' => $step_type->getUuid(),
        'type' => $step_type->getPluginId(),
        'weight' => $step_type->getWeight(),
        'step_description' => $step_type->getStepDescription(),
        'step_output_key' => $step_type->getStepOutputKey(),
        'output_type' => $step_type->getStepOutputType(),
        'uuid' => $step_type->getUuid(),
      ];

      $configuration = $step_type->getConfiguration();
      if (isset($configuration['data'])) {
        // Include required_steps
        $step_data['required_steps'] = $configuration['data']['required_steps'] ?? '';
        
        // Process step-specific configuration using the appropriate handler
        $step_handler = $this->stepHandlerManager->getHandler($step_data['type']);
        $step_handler->processStepData($step_data, $configuration['data'], $this->entityTypeManager);
        
        // Remove the 'data' key as we've extracted its contents
        unset($configuration['data']);
      }

      // Merge any remaining configuration
      $step_data = array_merge($step_data, $configuration);
      $pipeline_data['steps'][] = $step_data;
    }

    return new JsonResponse($pipeline_data);
  }

  /**
   * Gets the duration of an audio file in seconds.
   *
   * @param \Drupal\file\FileInterface $file
   *   The audio file.
   *
   * @return float|null
   *   The duration in seconds, or NULL if it couldn't be determined.
   */
  protected function getAudioDuration($file) {
    // Use ffprobe if available
    $real_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    if (function_exists('exec') && is_executable('/usr/bin/ffprobe')) {
      $command = "/usr/bin/ffprobe -i " . escapeshellarg($real_path) . " -show_entries format=duration -v quiet -of csv=\"p=0\"";
      $output = [];
      exec($command, $output, $return_var);

      if ($return_var === 0 && !empty($output[0]) && is_numeric($output[0])) {
        return (float) $output[0];
      }
    }

    // Fallback: Return null if we can't determine duration
    return null;
  }

}
