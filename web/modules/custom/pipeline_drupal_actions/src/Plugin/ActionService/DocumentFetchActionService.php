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
 * Provides a document fetch action service.
 *
 * @ActionService(
 *   id = "document_fetch",
 *   label = @Translation("Document Fetch Action")
 * )
 */
class DocumentFetchActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {

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
   * Constructs a new DocumentFetchActionService.
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration) {
    $form['rag_service_url'] = [
      '#type' => 'url',
      '#title' => $this->t('RAG Service URL'),
      '#description' => $this->t('The URL of the RAG service (e.g., http://rag-service-host)'),
      '#default_value' => $configuration['rag_service_url'] ?? '',
      '#required' => TRUE,
    ];

    $form['drupal_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Drupal URL'),
      '#description' => $this->t('The URL of the Drupal instance for file content retrieval'),
      '#default_value' => $configuration['drupal_url'] ?? '',
      '#required' => TRUE,
    ];

    $form['batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch Size'),
      '#description' => $this->t('Number of documents to process in each batch'),
      '#default_value' => $configuration['batch_size'] ?? 10,
      '#min' => 1,
      '#max' => 100,
      '#required' => TRUE,
    ];

    $form['status_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Document Status'),
      '#options' => [
        'pending' => $this->t('Pending'),
        'failed' => $this->t('Failed'),
        'all_unprocessed' => $this->t('All Unprocessed'),
      ],
      '#default_value' => $configuration['status_filter'] ?? 'pending',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    return [
      'rag_service_url' => $form_state->getValue('rag_service_url'),
      'drupal_url' => $form_state->getValue('drupal_url'),
      'batch_size' => $form_state->getValue('batch_size'),
      'status_filter' => $form_state->getValue('status_filter'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      $batch_size = $config['configuration']['batch_size'] ?? 10;
      $status_filter = $config['configuration']['status_filter'] ?? 'pending';

      // Query for unprocessed documents
      $query = $this->entityTypeManager->getStorage('media')->getQuery()
        ->accessCheck()
        ->condition('bundle', 'document')
        ->condition('field_rag_indexing_status', $this->getStatusConditions($status_filter), 'IN')
        ->range(0, $batch_size)
        ->sort('changed', 'ASC');

      $document_ids = $query->execute();

      if (empty($document_ids)) {
        return json_encode([
          'documents' => [],
          'count' => 0,
        ]);
      }

      $documents = $this->entityTypeManager->getStorage('media')->loadMultiple($document_ids);

      $processed = [];
      foreach ($documents as $document) {
        // Only process if we have a document file
        $file = $document->get('field_media_document')->entity;
        if (!$file) {
          $this->loggerFactory->get('pipeline')->warning('Document @id has no associated file', [
            '@id' => $document->id(),
          ]);
          continue;
        }
        // Get the absolute URL for the file
        $file_url = $this->getAbsoluteFileUrl($file);
        // Update document status
        $document->set('field_rag_indexing_status', 'processing');
        $document->set('field_last_indexed', \Drupal::time()->getRequestTime());
        $document->save();

        // Add to processed list
        $processed[] = [
          'mid' => $document->id(),
          'filename' => $file->getFilename(),
          'uri' => $file_url,
          'mime_type' => $file->getMimeType(),
          'size' => $file->getSize(),
        ];
      }

      $result = [
        'documents' => $processed,
        'count' => count($processed),
        'timestamp' => \Drupal::time()->getCurrentTime(),
      ];

      $this->loggerFactory->get('pipeline')->info('Fetched @count documents for RAG processing', [
        '@count' => count($processed),
      ]);

      return json_encode($result);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Document fetch error: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Gets the absolute URL for a file entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   *
   * @return string
   *   The absolute URL for the file.
   */
  protected function getAbsoluteFileUrl($file): string
  {
    $file_uri = $file->getFileUri();

    // Convert public:// scheme to actual URL
    if (str_starts_with($file_uri, 'public://')) {
      $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri);

      // Ensure we're using the configured domain if available
      $request = \Drupal::request();
      $current_base_url = $request->getSchemeAndHttpHost();

      // If we have a base URL configured in settings.php, use that instead
      $configured_base_url = \Drupal::config('system.site')->get('base_url');
      if (!empty($configured_base_url)) {
        $file_url = str_replace($current_base_url, $configured_base_url, $file_url);
      }

      return $file_url;
    }

    return $file_uri;
  }

  /**
   * Get status conditions based on filter.
   */
  protected function getStatusConditions($filter) {
    switch ($filter) {
      case 'failed':
        return ['failed'];
      case 'all_unprocessed':
        return ['pending', 'failed'];
      case 'pending':
      default:
        return ['pending'];
    }
  }

}
