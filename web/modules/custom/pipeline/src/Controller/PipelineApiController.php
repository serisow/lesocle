<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pipeline\Entity\LLMConfig;
use Drupal\pipeline\Plugin\ModelManager;
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
   * Constructs a PipelineApiController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\pipeline\Plugin\ModelManager $model_manager
   *   The model manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModelManager $model_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->modelManager = $model_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.model_manager')
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

    // Query for enabled pipelines with scheduled times
    $query = $pipeline_storage->getQuery()
      ->condition('status', TRUE)
      ->condition('scheduled_time', 0, '>')
      ->sort('scheduled_time', 'ASC');
    $pipeline_ids = $query->execute();

    $scheduled_pipelines = [];
    foreach ($pipeline_ids as $id) {
      $pipeline = $pipeline_storage->load($id);
      $scheduled_pipelines[] = [
        'id' => $pipeline->id(),
        'scheduled_time' => $pipeline->getScheduledTime(),
      ];
    }

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
      'scheduled_time' => $pipeline->getScheduledTime(),
      'steps' => [],
    ];

    foreach ($pipeline->getStepTypes() as $step_type) {
      $step_data = [
        'id' => $step_type->getUuid(),
        'type' => $step_type->getPluginId(),
        'weight' => $step_type->getWeight(),
        'step_description' => $step_type->getStepDescription(),
        'step_output_key' => $step_type->getStepOutputKey(),
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
            $step_data['response'] = $configuration['data']['response'] ?? '';
            $step_data['llm_config'] = $configuration['data']['llm_config'] ?? '';

            if (isset($configuration['data']['llm_config'])) {
              $llm_config = LLMConfig::load($configuration['data']['llm_config']);
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
  /**
   * Cleans the step configuration for API output.
   *
   * @param array $configuration
   *   The step configuration array.
   *
   * @return array
   *   The cleaned configuration array.
   */
  protected function cleanStepConfiguration(array $configuration) {
    // Remove internal Drupal keys
    unset($configuration['id']);
    unset($configuration['uuid']);
    unset($configuration['weight']);

    // Clean the data array
    if (isset($configuration['data'])) {
      // Remove any sensitive information
      unset($configuration['data']['api_key']);

      // Flatten the data array for easier consumption
      $configuration = array_merge($configuration, $configuration['data']);
      unset($configuration['data']);
    }

    return $configuration;
  }
}
