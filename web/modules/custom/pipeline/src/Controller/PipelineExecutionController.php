<?php
/**
 * Handles execution results from the Go service.
 *
 * This controller is the primary bridge between the Go service and Drupal,
 * specifically handling the reception and processing of pipeline execution
 * results from the Go service.
 *
 * Key responsibilities:
 * - Receives and validates execution results from Go service
 * - Creates and updates PipelineRun entities
 * - Processes step results including special output types (images, etc.)
 * - Handles Drupal-side actions marked for Drupal execution
 * - Manages execution failure tracking
 *
 * Critical behaviors:
 * - Processes both successful and failed executions
 * - Handles media entity creation for image outputs
 * - Manages pipeline failure thresholds and auto-disabling
 * - Provides detailed execution feedback to Go service
 *
 * Response structure:
 * - Returns standardized JSON responses with execution context
 * - Includes pipeline status updates
 * - Provides execution summaries and step results
 * - Returns relevant Drupal entity IDs and URLs
 *
 * Error handling:
 * - Creates detailed log files for failures
 * - Manages pipeline failure counts
 * - Provides error context back to Go service
 * - Handles partial execution states
 *
 * @see \Drupal\pipeline\Entity\PipelineRun
 * @see \Drupal\pipeline\Service\PipelineErrorHandler
 */

namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pipeline\Plugin\ActionServiceManager;
use Drupal\pipeline\Service\GoServiceImageProcessor;
use Drupal\pipeline\Service\ImageDownloadService;
use Drupal\pipeline\Service\PipelineErrorHandler;
use Drupal\pipeline\Service\VideoDownloadService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\pipeline\Entity\PipelineInterface;

class PipelineExecutionController extends ControllerBase {

  protected $entityTypeManager;
  protected $actionServiceManager;

  /**
   *
   * @var \Drupal\pipeline\Service\ImageDownloadService
   */
  protected $imageDownloadService;

  /**
   * The video download service.
   *
   * @var \Drupal\pipeline\Service\VideoDownloadService
   */
  protected $videoDownloadService;

  /** @var \Drupal\pipeline\Service\PipelineErrorHandler */
  protected $errorHandler;

  /**
   * The Go service image processor.
   *
   * @var \Drupal\pipeline\Service\GoServiceImageProcessor
   */
  protected $goServiceImageProcessor;


  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ActionServiceManager $action_service_manager,
    ImageDownloadService $image_download_service,
    VideoDownloadService $video_download_service,
    PipelineErrorHandler $error_handler,
    GoServiceImageProcessor $go_service_image_processor

  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->actionServiceManager = $action_service_manager;
    $this->imageDownloadService = $image_download_service;
    $this->videoDownloadService = $video_download_service;
    $this->errorHandler = $error_handler;
    $this->goServiceImageProcessor = $go_service_image_processor;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.action_service'),
      $container->get('pipeline.image_download_service'),
      $container->get('pipeline.video_download_service'),
      $container->get('pipeline.error_handler'),
      $container->get('pipeline.go_service_image_processor')
    );
  }


  public function receiveExecutionResult(Request $request, PipelineInterface $pipeline) {
    $data = json_decode($request->getContent(), TRUE);

    if (!$data) {
      return new JsonResponse([
        'error' => 'Invalid JSON data',
        'context' => [
          'pipeline_id' => $pipeline->id(),
          'pipeline_label' => $pipeline->label(),
          'request_time' => \Drupal::time()->getCurrentTime(),
          'content_type' => $request->headers->get('Content-Type'),
          'content_length' => $request->headers->get('Content-Length'),
          'raw_content' => substr($request->getContent(), 0, 255) . '...',
        ],
        'status' => 'error',
        'code' => 400,
      ], 400);
    }

    $step_results = $data['step_results'] ?? [];
    // Sort step_results by weight
    uasort($step_results, function($a, $b) {
      return $a['sequence'] <=> $b['sequence'];
    });

    // Create PipelineRun entity
    /** @var \Drupal\pipeline_run\Entity\PipelineRun $pipeline_run */
    $pipeline_run = $this->entityTypeManager->getStorage('pipeline_run')->create([
      'status' => $data['success'] ? 'completed' : 'failed',
      'start_time' => $data['start_time'] ?? \Drupal::time()->getCurrentTime(),
      'end_time' => $data['end_time'] ?? \Drupal::time()->getCurrentTime(),
      'created_by' => \Drupal::currentUser()->id(),
      'triggered_by' => 'api',
      'step_results' => json_encode([]), // Initialize empty step results
    ]);
    $pipeline_run->save();

    $context = ['results' => $step_results];
    $has_errors = false;

    foreach ($step_results as $step_uuid => $result) {
      $step_type = $pipeline->getStepType($step_uuid);
      if ($step_type) {
        $start_time = $result['start_time'] ?? \Drupal::time()->getCurrentTime();
        $end_time = $result['end_time'] ?? \Drupal::time()->getCurrentTime();

        // Start error capture for this step
        $this->errorHandler->startErrorCapture($step_uuid);

        try {
          // Create PipelineStepRun entity
          $step_result = [
            'step_uuid' => $step_uuid,
            'step_description' => $result['step_description'],
            'status' => $result['status'] ?? 'completed',
            'start_time' => $start_time,
            'end_time' => $end_time,
            'duration' => $end_time - $start_time,
            'step_type' => $step_type->getPluginId(),
            'sequence' => $step_type->getWeight(),
            'data' => $result['data'],
            'output_type' => $result['output_type'],
            'error_message' => $result['error_message'] ?? '',
          ];

          $config = $step_type->getConfiguration();

          if ($step_type->getPluginId() === 'action_step') {
            $action_config_id = $config['data']['action_config'];
            /** @var \Drupal\pipeline\Entity\ActionConfig $action_config */
            $action_config = $this->entityTypeManager->getStorage('action_config')->load($action_config_id);
            if ($action_config) {
              // Only execute if this is a Drupal-side action or if execution_location is not set (backward compatibility)
              $execution_location = $action_config->get('execution_location') ?? 'drupal';
              if ($execution_location === 'drupal') {
                $action_service_id = $action_config->getActionService();
                $action_service = $this->actionServiceManager->createInstance($action_service_id);
                try {
                  $action_result = $action_service->executeAction($action_config->toArray(), $context);
                  $step_result['data'] = $action_result;
                  $step_result['status'] = 'completed';
                  $step_result['executed_in'] = 'drupal';
                }
                catch (\Exception $e) {
                  $has_errors = true;
                  $step_result['status'] = 'failed';
                  $step_result['error_message'] = $e->getMessage();
                  \Drupal::logger('pipeline')->error('Error executing action: @error', ['@error' => $e->getMessage()]);
                }
              } else {
                // For Go-executed actions, use the result directly
                $step_result['executed_in'] = 'go';
              }
            }
          }

          $step_results[$step_uuid] = $step_result;
          $context['results'][$step_uuid] = $step_result;

          // Handle featured image
          if ($result['output_type'] === 'featured_image') {
            // Add this condition to check if the step type is NOT UploadImageStep
            if ($step_type->getPluginId() !== 'upload_image_step') {
              $image_url = $result['data'];

              // Check if data is a JSON string containing an image URL
              if (is_string($image_url) && $this->isJson($image_url)) {
                $image_data_obj = json_decode($image_url, TRUE);
                if (isset($image_data_obj['url'])) {
                  $image_url = $image_data_obj['url'];
                }
              }

              $image_data = $this->imageDownloadService->downloadImage($image_url);
              $step_result['data'] = $image_data;
              $step_results[$step_uuid] = $step_result;
              $context['results'][$step_uuid]['data'] = $image_data;
            }
          }
          // Handle video content
          elseif ($result['output_type'] === 'video_content') {
            try {
              $video_data = $this->videoDownloadService->downloadVideo($result['data']);
              $step_result['data'] = $video_data;
              $step_results[$step_uuid] = $step_result;
              $context['results'][$step_uuid]['data'] = $video_data;
            }
            catch (\Exception $e) {
              $has_errors = true;
              $step_result['status'] = 'failed';
              $step_result['error_message'] = $e->getMessage();
              \Drupal::logger('pipeline')->error('Error processing video: @error', ['@error' => $e->getMessage()]);
            }
          }
          // Special handling for NewsItemImageGeneratorActionService
          elseif ($result['action_service'] == 'news_item_image_generator' && isset($result['data'])) {
            $news_items_data = $this->goServiceImageProcessor->processNewsItemsWithImages($result['data']);
            $step_result['data'] = $news_items_data;
            $step_results[$step_uuid] = $step_result;
            $context['results'][$step_uuid]['data'] = $news_items_data;
          }

        } catch (\Exception $e) {
          $has_errors = true;
          $step_result['status'] = 'failed';
          $step_result['error_message'] = $e->getMessage();
          \Drupal::logger('pipeline')->error('Error in step execution: @error', ['@error' => $e->getMessage()]);
        } finally {
          // Stop error capture
          $this->errorHandler->stopErrorCapture();
        }
      }
    }

    if (!$data['success']) {
      $pipeline_run->set('status', 'failed');
      if ($log_file = $this->errorHandler->createLogFile($step_results, $pipeline_run->id())) {
        $pipeline_run->setLogFile($log_file->id());
      }
      $pipeline->incrementExecutionFailures();

      // Add these lines to ensure config is saved properly
      $failures = $pipeline->getExecutionFailures();
      $pipeline->save();

      // Force config cache clear for this entity
      \Drupal::service('config.factory')->reset('pipeline.pipeline.' . $pipeline->id());

      if ($failures >= 3) {
        \Drupal::logger('pipeline')->error('Pipeline %pipeline has failed %count consecutive times and will be skipped until reset.', [
          '%pipeline' => $pipeline->label(),
          '%count' => $failures,
        ]);
      }
    } else {
      $pipeline_run->set('status', 'completed');
      $pipeline->resetExecutionFailures();
      $pipeline->save();
      // Force config cache clear for this entity
      \Drupal::service('config.factory')->reset('pipeline.pipeline.' . $pipeline->id());
    }

    $pipeline_run->set('end_time', \Drupal::time()->getCurrentTime());
    $pipeline_run->set('step_results', json_encode($step_results));

    $pipeline_run->save();

    // Allow the go service to have context from the Drupal side
    $response_data = [
      'message' => 'Execution results processed successfully',
      'context' => [
        'pipeline_id' => $pipeline->id(),
        'pipeline_label' => $pipeline->label(),
        'execution_summary' => [
          'start_time' => $pipeline_run->getStartTime(),
          'end_time' => $pipeline_run->getEndTime(),
          'duration' => $pipeline_run->getDuration(),
          'total_steps' => count($step_results),
          'status' => $pipeline_run->getStatus(),
          'triggered_by' => $pipeline_run->getTriggeredBy(),
        ],
        'steps_summary' => [
          'completed' => count(array_filter($step_results, function($step) {
            return $step['status'] === 'completed';
          })),
          'failed' => count(array_filter($step_results, function($step) {
            return $step['status'] === 'failed';
          })),
        ],
        'run_id' => $pipeline_run->id(),
        'has_errors' => $has_errors,
        'log_file' => $has_errors ? $pipeline_run->getLogFile()?->createFileUrl() : null,
        'execution_failures' => $pipeline->getExecutionFailures(),
        'current_status' => [
          'execution_enabled' => $pipeline->getExecutionFailures() < 3,
          'failure_count' => $pipeline->getExecutionFailures(),
        ],
      ],
      'status' => 'success',
      'code' => 200,
    ];


    return new JsonResponse($response_data);
  }

  /**
   * Helper method to check if a string is valid JSON.
   */
  private function isJson($string): bool
  {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }
}
