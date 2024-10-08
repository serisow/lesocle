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

    foreach ($step_results as $step_uuid => $result) {
      $step_type = $pipeline->getStepType($step_uuid);
      if ($step_type) {
        $config = $step_type->getConfiguration();
        $config['data']['response'] = $result['output'];
        $step_type->setConfiguration($config);

        if ($step_type->getPluginId() === 'action_step') {
          $action_config_id = $config['data']['action_config'];
          $action_config = $this->entityTypeManager->getStorage('action_config')->load($action_config_id);
          if ($action_config) {
            $action_service_id = $action_config->getActionService();
            $action_service = $this->actionServiceManager->createInstance($action_service_id);
            $context = ['last_response' => $result['output']];
            try {
              $action_result = $action_service->executeAction($action_config->toArray(), $context);
              // You might want to log or store $action_result
            }
            catch (\Exception $e) {
              // Log the error or handle it as appropriate
              \Drupal::logger('pipeline')->error('Error executing action: @error', ['@error' => $e->getMessage()]);
            }
          }
        }
      }
    }
    $pipeline->save();

    return new JsonResponse(['message' => 'Execution results processed successfully']);
  }

}
