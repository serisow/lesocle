<?php
namespace Drupal\pipeline_drupal_actions\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a document update action service.
 *
 * @ActionService(
 *   id = "document_update",
 *   label = @Translation("Document Update Action")
 * )
 */
class DocumentUpdateActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new DocumentUpdateActionService.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      // Find update document content in the results using output_type
      $update_data = null;
      foreach ($context['results'] as $step_result) {
        if ($step_result['output_type'] === 'fetch_document_content') {
          $update_data = $step_result['data'];
          break;
        }
      }

      if (!$update_data) {
        throw new \Exception('No update document content found in pipeline context');
      }

      // Clean and parse the JSON content if needed
      if (is_string($update_data)) {
        // Remove ```json prefix and ``` suffix if present
        $update_data = preg_replace('/^```json\s*|\s*```$/s', '', $update_data);
        // Trim any whitespace
        $update_data = trim($update_data);
        $data = json_decode($update_data, TRUE);
        if (json_last_error() !== JSON_ERROR_NONE) {
          throw new \Exception('Invalid JSON format in update document content');
        }
      } else {
        $data = $update_data;
      }

      $indexed_documents = $data['indexed_documents'] ?? [];
      $updated_count = 0;

      foreach ($indexed_documents as $doc) {
        $media = $this->entityTypeManager->getStorage('media')->load($doc['mid']);
        if (!$media) {
          $this->loggerFactory->get('pipeline')->warning('Document not found: @mid', [
            '@mid' => $doc['mid'],
          ]);
          continue;
        }

        // Set status based on the processing result
        $status = $doc['status'] ?? 'failed';
        $media->set('field_rag_indexing_status', $status);

        // Only set embedding ID and metadata for successfully indexed documents
        if ($status === 'indexed') {
          if (isset($doc['document_id'])) {
            $media->set('field_embedding_id', $doc['document_id']);
          }

          if (isset($doc['metadata']) && !empty($doc['metadata'])) {
            $media->set('field_rag_metadata', json_encode($doc['metadata']));
          }
        } else {
          // For failed documents, clear embedding ID and metadata
          $media->set('field_embedding_id', null);
          $media->set('field_rag_metadata', '{}');
        }

        $media->set('field_last_indexed', \Drupal::time()->getRequestTime());
        $media->save();
        $updated_count++;
      }

      $result = [
        'updated_documents' => $indexed_documents,
        'count' => $updated_count,
        'timestamp' => \Drupal::time()->getRequestTime(),
      ];

      $this->loggerFactory->get('pipeline')->info('Updated @count documents with indexing results and metadata', [
        '@count' => $updated_count,
      ]);

      return json_encode($result);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Document update error: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration) {
    // No configuration needed for this service.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // No configuration to submit.
    return [];
  }
}
