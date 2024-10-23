<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pipeline\Plugin\ActionServiceManager;
use Drupal\pipeline\Service\ImageDownloadService;
use Drupal\pipeline\Service\PipelineErrorHandler;
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

  /** @var \Drupal\pipeline\Service\PipelineErrorHandler */
  protected $errorHandler;


  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ActionServiceManager $action_service_manager,
    ImageDownloadService $image_download_service,
    PipelineErrorHandler $error_handler
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->actionServiceManager = $action_service_manager;
    $this->imageDownloadService = $image_download_service;
    $this->errorHandler = $error_handler;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.action_service'),
      $container->get('pipeline.image_download_service'),
      $container->get('pipeline.error_handler')
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
    $pipeline_run = $this->entityTypeManager->getStorage('pipeline_run')->create([
      'pipeline_id' => $pipeline->id(),
      'status' => 'completed', // Assuming the Go service completes the pipeline
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
            $action_config = $this->entityTypeManager->getStorage('action_config')->load($action_config_id);
            if ($action_config) {
              $action_service_id = $action_config->getActionService();
              $action_service = $this->actionServiceManager->createInstance($action_service_id);
              try {
                $action_result = $action_service->executeAction($action_config->toArray(), $context);
                $step_result['data'] = $action_result;
                $step_result['status'] = 'completed';
              }
              catch (\Exception $e) {
                $has_errors = true;
                $step_result['status'] = 'failed';
                $step_result['error_message'] = $e->getMessage();
                \Drupal::logger('pipeline')->error('Error executing action: @error', ['@error' => $e->getMessage()]);
              }
            }
          }

          $step_results[$step_uuid] = $step_result;
          $context['results'][$step_uuid] = $step_result;

          // Handle featured image
          if ($result['output_type'] === 'featured_image') {
            $image_data = $this->imageDownloadService->downloadImage($result['data']);
            $step_result['data'] = $image_data;
            $step_results[$step_uuid] = $step_result;
            $context['results'][$step_uuid]['data'] = $image_data;
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

    // Create log file if there are errors
    if ($has_errors) {
      $pipeline_run->set('status', 'failed');
      if ($log_file = $this->errorHandler->createLogFile($step_results, $pipeline_run->id())) {
        $pipeline_run->setLogFile($log_file->id());
      }
    } else {
      $pipeline_run->set('status', 'completed');
    }

    $pipeline_run->set('end_time', \Drupal::time()->getCurrentTime());
    $pipeline_run->set('step_results', json_encode($step_results));
    $pipeline_run->save();

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
      ],
      'status' => 'success',
      'code' => 200,
    ];

    return $response_data;
  }
}
