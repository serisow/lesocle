<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pipeline\Plugin\ActionServiceManager;
use Drupal\pipeline\Service\ImageDownloadService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\pipeline\Entity\PipelineInterface;

class PipelineExecutionController extends ControllerBase {

  protected $entityTypeManager;
  protected $actionServiceManager;

  /**
   * @var \Drupal\pipeline\Service\ImageDownloadService
   */
  protected $imageDownloadService;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ActionServiceManager $action_service_manager,
    ImageDownloadService $image_download_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->actionServiceManager = $action_service_manager;
    $this->imageDownloadService = $image_download_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.action_service'),
      $container->get('pipeline.image_download_service')
    );
  }


  public function receiveExecutionResult(Request $request, PipelineInterface $pipeline) {
    $data = json_decode($request->getContent(), TRUE);

    if (!$data) {
      return new JsonResponse(['error' => 'Invalid JSON data'], 400);
    }

    $step_results = $data['step_results'] ?? [];
    // Sort step_results by weight
    uasort($step_results, function($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    // Create PipelineRun entity
    $pipeline_run = $this->entityTypeManager->getStorage('pipeline_run')->create([
      'pipeline_id' => $pipeline->id(),
      'status' => 'completed', // Assuming the Go service completes the pipeline
      'start_time' => $data['start_time'] ?? \Drupal::time()->getCurrentTime(),
      'end_time' => $data['end_time'] ?? \Drupal::time()->getCurrentTime(),
      'created_by' => \Drupal::currentUser()->id(),
      'triggered_by' => 'api',
    ]);
    $pipeline_run->save();

    $context = ['results' => $step_results];

    foreach ($step_results as $step_uuid => $result) {
      $step_type = $pipeline->getStepType($step_uuid);
      if ($step_type) {
        // Create PipelineStepRun entity
        $step_run = $this->entityTypeManager->getStorage('pipeline_step_run')->create([
          'pipeline_run_id' => $pipeline_run->id(),
          'step_uuid' => $step_uuid,
          'status' => $result['status'] ?? 'completed',
          'start_time' => $result['start_time'] ?? \Drupal::time()->getCurrentTime(),
          'end_time' => $result['end_time'] ?? \Drupal::time()->getCurrentTime(),
          'step_type' => $step_type->getPluginId(),
          'sequence' => $step_type->getWeight(),
          'output' => $result['data'],
          'error_message' => $result['error_message'] ?? '',
        ]);
        $step_run->save();

        // Handle featured image
        if ($result['output_type'] === 'featured_image') {
          $image_data = $this->imageDownloadService->downloadImage($result['data']);
          $result['data'] = $image_data;
          $context['results'][$step_uuid]['data'] = $image_data;
          $step_run->set('output', $image_data);
          $step_run->set('status', 'success');
          $step_run->save();
        }
        $config = $step_type->getConfiguration();
        $config['data']['response'] = $result['data'];
        $step_type->setConfiguration($config);

        if ($step_type->getPluginId() === 'action_step') {
          $action_config_id = $config['data']['action_config'];
          $action_config = $this->entityTypeManager->getStorage('action_config')->load($action_config_id);
          if ($action_config) {
            $action_service_id = $action_config->getActionService();
            $action_service = $this->actionServiceManager->createInstance($action_service_id);
            try {
              $action_result = $action_service->executeAction($action_config->toArray(), $context);
              // Update the step run with the action result
              $step_run->set('output', $action_result);
              $step_run->set('status', 'success');
              $step_run->save();

            }
            catch (\Exception $e) {
              // Log the error or handle it as appropriate
              \Drupal::logger('pipeline')->error('Error executing action: @error', ['@error' => $e->getMessage()]);
              $step_run->set('status', 'failed');
              $step_run->set('error_message', $e->getMessage());
              $step_run->save();

              // Update PipelineRun status if a step fails
              $pipeline_run->set('status', 'failed');
              $pipeline_run->set('error_message', 'Error in step: ' . $step_type->getPluginId());
              $pipeline_run->save();
            }
          }
        }
      }
    }
    $pipeline->save();

    return new JsonResponse(['message' => 'Execution results processed successfully']);
  }

}
