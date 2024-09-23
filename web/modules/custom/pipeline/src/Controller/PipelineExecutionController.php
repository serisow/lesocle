<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\pipeline\Entity\PipelineInterface;

class PipelineExecutionController extends ControllerBase {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
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

        if ($step_type->getPluginId() === 'action_step' && $config['data']['action_config'] === 'create_article_action') {
          $this->createArticleEntity($result['output']);
        }
      }
    }

    $pipeline->save();

    return new JsonResponse(['message' => 'Execution results processed successfully']);
  }

  protected function createArticleEntity($content) {
    // Decode the JSON content
    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON format: " . json_last_error_msg());
    }

    if (!isset($data['title']) || !isset($data['body'])) {
      throw new \Exception("JSON must contain 'title' and 'body' fields");
    }

    $title = $data['title'];
    $body = $data['body'];

    // Ensure title is not empty and not too long
    $title = !empty($title) ? $title : 'Untitled Article';
    $title = substr($title, 0, 255);

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->create([
      'type' => 'article',
      'title' => $title,
      'body' => [
        'value' => $body,
        'format' => 'full_html',
      ],
    ]);
    $node->save();

    return $node->id();
  }
}
