<?php

/**
 * Provides API endpoints for Go service interaction.
 *
 * This controller exposes the necessary endpoints for the Go service to fetch
 * pipeline configurations and manage scheduled executions. It serves as the
 * primary interface for the Go service to interact with Drupal's pipeline system.
 *
 * Key endpoints:
 * - GET /api/pipelines/scheduled: Returns scheduled pipelines due for execution
 * - GET /api/pipelines/{id}: Returns complete pipeline configuration
 *
 * Critical functionalities:
 * - Filters and returns scheduled pipelines based on execution criteria
 * - Validates pipeline execution eligibility (status, failure counts)
 * - Provides complete pipeline configurations including steps
 * - Handles authentication and access control(@TODO: SSOW - THIS IS NEED TO BE COMPLETED)
 *
 * Pipeline scheduling logic:
 * - Handles both one-time and recurring schedules
 * - Validates execution windows
 * - Checks failure thresholds
 * - Manages execution timing
 *
 * Response formats:
 * - Returns detailed pipeline configurations
 * - Includes step configurations and dependencies
 * - Provides LLM and action configurations
 * - Includes execution history and status
 *
 * Note: This controller is specifically designed for machine-to-machine
 * communication with the Go service and implements appropriate security
 * and validation measures.
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
   * Constructs a PipelineApiController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\pipeline\Plugin\ModelManager $model_manager
   *   The model manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   * The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ModelManager $model_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->modelManager = $model_manager;
    $this->logger = $logger_factory->get('pipeline');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.model_manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * Returns a list of scheduled pipelines.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing scheduled pipelines.
   */
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

        // Handle specific step types
        switch ($step_data['type']) {
          case 'llm_step':
            $step_data['prompt'] = $configuration['data']['prompt'] ?? '';
            $step_data['llm_config'] = $configuration['data']['llm_config'] ?? '';

            if (isset($configuration['data']['llm_config'])) {
              $llm_config = $this->entityTypeManager->getStorage('llm_config')->load($configuration['data']['llm_config']);
              if ($llm_config) {
                $model_plugin = $this->modelManager->createInstanceFromModelName($llm_config->getModelName());
                $step_data['llm_service'] = [
                  'id' => $llm_config->id(),
                  'label' => $llm_config->label(),
                  'api_key' => $llm_config->getApiKey(),
                  'model_name' => $llm_config->getModelName(),
                  'api_url' => $llm_config->getApiUrl(),
                  'parameters' => $llm_config->getParameters(),
                  'service_name' => $model_plugin->getServiceId(),
                ];
              }
            }
            break;

          case 'action_step':
            $step_data['action_config'] = $configuration['data']['action_config'] ?? '';
            // Load and add action configuration details
            if (isset($configuration['data']['action_config'])) {
              $action_config = $this->entityTypeManager->getStorage('action_config')
                ->load($configuration['data']['action_config']);

              if ($action_config) {
                $action_configuration = $action_config->getConfiguration();
                if (empty($action_configuration)) {
                  $action_configuration = (object) [];
                }
                $step_data['action_details'] = [
                  'id' => $action_config->id(),
                  'label' => $action_config->label(),
                  'action_service' => $action_config->getActionService(),
                  'execution_location' => $action_config->get('execution_location') ?? 'drupal',
                  'configuration' => $action_configuration,
                ];
              }
            }
            break;

          case 'google_search':
            $step_data['google_search_config'] = [
              'query' => $configuration['data']['query'] ?? '',
              'category' => $configuration['data']['category'] ?? '',
              'advanced_params' => $configuration['data']['advanced_params'] ?? [],
            ];
            break;
        }

        // Remove the 'data' key as we've extracted its contents
        unset($configuration['data']);
      }

      // Merge any remaining configuration
      $step_data = array_merge($step_data, $configuration);

      $pipeline_data['steps'][] = $step_data;
    }

    return new JsonResponse($pipeline_data);
  }

  public function fetchDocuments(Request $request) {
    $batch_size = $request->query->get('batch_size', 10);
    $status = $request->query->get('status', 'pending');

    $query = $this->entityTypeManager->getStorage('media')->getQuery()
      ->accessCheck()
      ->condition('bundle', 'document')
      ->condition('field_rag_indexing_status', $status)
      ->range(0, $batch_size)
      ->sort('changed', 'ASC');

    $document_ids = $query->execute();
    $documents = [];

    if (!empty($document_ids)) {
      $media_entities = $this->entityTypeManager->getStorage('media')
        ->loadMultiple($document_ids);

      foreach ($media_entities as $media) {
        $file = $media->get('field_media_document')->entity;
        if ($file) {
          $documents[] = [
            'mid' => $media->id(),
            'filename' => $file->getFilename(),
            'uri' => $file->createFileUrl(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
          ];

          // Update status to processing
          $media->set('field_rag_indexing_status', 'processing');
          $media->save();
        }
      }
    }

    return new JsonResponse([
      'documents' => $documents,
      'count' => count($documents),
      'timestamp' => \Drupal::time()->getCurrentTime(),
    ]);
  }

}
