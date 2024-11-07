<?php

namespace Drupal\pipeline\Plugin\StepType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Document Search' step type.
 *
 * @StepType(
 *   id = "document_search",
 *   label = @Translation("Document Search Step"),
 *   description = @Translation("Search documents using vector similarity for RAG integration.")
 * )
 */
class DocumentSearchStep extends ConfigurableStepTypeBase implements StepTypeExecutableInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient = $container->get('http_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return [
      'search_input' => '',
      'search_settings' => [
        'similarity_threshold' => 0.8,
        'max_results' => 5,
        'similarity_metric' => 'cosine',
      ],
      'content_settings' => [
        'include_metadata' => TRUE,
        'min_word_count' => 0,
        'exclude_already_used' => FALSE,
      ],
      'output_type' => 'document_search_result',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::additionalConfigurationForm($form, $form_state);

    $form['search_input'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Search Input'),
      '#description' => $this->t('The text to search for. Use placeholders like {step_key} to incorporate results from previous steps.'),
      '#default_value' => $this->configuration['search_input'],
      '#required' => TRUE,
    ];

    $form['search_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Search Settings'),
      '#open' => TRUE,
    ];

    $form['search_settings']['similarity_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Similarity Threshold'),
      '#description' => $this->t('Minimum similarity score (0-1) for including results.'),
      '#default_value' => $this->configuration['search_settings']['similarity_threshold'],
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
      '#required' => TRUE,
    ];

    $form['search_settings']['max_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Results'),
      '#description' => $this->t('Maximum number of similar documents to return.'),
      '#default_value' => $this->configuration['search_settings']['max_results'],
      '#min' => 1,
      '#max' => 50,
      '#required' => TRUE,
    ];

    $form['search_settings']['similarity_metric'] = [
      '#type' => 'select',
      '#title' => $this->t('Similarity Metric'),
      '#options' => [
        'cosine' => $this->t('Cosine Similarity'),
        'euclidean' => $this->t('Euclidean Distance'),
        'inner_product' => $this->t('Inner Product'),
      ],
      '#default_value' => $this->configuration['search_settings']['similarity_metric'],
      '#description' => $this->t('Method used to calculate similarity between vectors.'),
      '#required' => TRUE,
    ];

    $form['content_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Settings'),
      '#open' => TRUE,
    ];

    $form['content_settings']['include_metadata'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include Metadata'),
      '#description' => $this->t('Include document metadata in results.'),
      '#default_value' => $this->configuration['content_settings']['include_metadata'],
    ];

    $form['content_settings']['min_word_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Word Count'),
      '#description' => $this->t('Minimum number of words for included documents.'),
      '#default_value' => $this->configuration['content_settings']['min_word_count'],
      '#min' => 0,
    ];

    $form['content_settings']['exclude_already_used'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude Previously Used'),
      '#description' => $this->t('Exclude documents already used in previous steps.'),
      '#default_value' => $this->configuration['content_settings']['exclude_already_used'],
    ];

    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['search_input'] = $form_state->getValue(['data', 'search_input']);

    // Search settings
    $this->configuration['search_settings'] = [
      'similarity_threshold' => $form_state->getValue(['data', 'search_settings', 'similarity_threshold']),
      'max_results' => $form_state->getValue(['data', 'search_settings', 'max_results']),
      'similarity_metric' => $form_state->getValue(['data', 'search_settings', 'similarity_metric']),
    ];

    // Content settings
    $this->configuration['content_settings'] = [
      'include_metadata' => (bool) $form_state->getValue(['data', 'content_settings', 'include_metadata']),
      'min_word_count' => $form_state->getValue(['data', 'content_settings', 'min_word_count']),
      'exclude_already_used' => (bool) $form_state->getValue(['data', 'content_settings', 'exclude_already_used']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string {
    try {
      // Get search input with placeholders replaced
      $search_input = $this->processPlaceholders($this->configuration['search_input'], $context);

      // Prepare request payload
      $payload = [
        'query' => $search_input,
        'config' => [
          'similarity_threshold' => $this->configuration['search_settings']['similarity_threshold'],
          'max_results' => $this->configuration['search_settings']['max_results'],
          'similarity_metric' => $this->configuration['search_settings']['similarity_metric'],
          'include_metadata' => $this->configuration['content_settings']['include_metadata'],
          'min_word_count' => $this->configuration['content_settings']['min_word_count'],
          'exclude_already_used' => $this->configuration['content_settings']['exclude_already_used'],
        ]
      ];

      // If excluding already used documents, add their IDs to the payload
      if ($this->configuration['content_settings']['exclude_already_used']) {
        $used_docs = $this->getUsedDocumentIds($context);
        if (!empty($used_docs)) {
          $payload['exclude_documents'] = $used_docs;
        }
      }

      try {
        // Make request to Go service
        $response = $this->httpClient->post(
          'http://lesoclego-dev.sa/documents/search',
          [
            'headers' => [
              'Content-Type' => 'application/json',
              //'Host' => 'www.lesoclego-dev.com',
            ],
            'json' => $payload,
            'timeout' => 30,
          ]
        );

        $data = json_decode($response->getBody(), TRUE);

        if (json_last_error() !== JSON_ERROR_NONE) {
          throw new \Exception('Invalid JSON response from search service');
        }

        // Process the search results
        $processed_results = [];
        if (isset($data['documents']) && !empty($data['documents'])) {
          foreach ($data['documents'] as $doc) {
            $result = [
              'document_id' => $doc['document_id'],
              'content' => $doc['content'],
              'similarity_score' => $doc['similarity_score'],
            ];

            // Add metadata if requested
            if ($this->configuration['content_settings']['include_metadata']) {
              // Load media entity using field_embedding_id
              $media_entities = $this->entityTypeManager
                ->getStorage('media')
                ->loadByProperties(['field_embedding_id' => $doc['document_id']]);

              if ($media_entities) {
                $media = reset($media_entities);
                $result['metadata'] = [
                  'filename' => $media->getName(),
                  'mid' => $media->id(),
                  'created' => $media->getCreatedTime(),
                  'changed' => $media->getChangedTime(),
                  'rag_metadata' => json_decode($media->get('field_rag_metadata')->value ?? '{}', TRUE),
                ];
              }
            }

            $processed_results[] = $result;
          }
        }

        // Prepare final result
        $final_result = [
          'similar_documents' => $processed_results,
          'count' => count($processed_results),
          'search_settings' => [
            'threshold' => $this->configuration['search_settings']['similarity_threshold'],
            'metric' => $this->configuration['search_settings']['similarity_metric'],
            'max_results' => $this->configuration['search_settings']['max_results'],
          ],
        ];

        // Store in context with proper output type
        $context['results'][$this->getStepOutputKey()] = [
          'output_type' => $this->getStepOutputType(),
          'service' => 'document_search',
          'data' => json_encode($final_result),
        ];

        return json_encode($final_result);

      }
      catch (\GuzzleHttp\Exception\RequestException $e) {
        $this->logger->error('Document search request failed: @error', [
          '@error' => $e->getMessage(),
          '@query' => $search_input,
        ]);
        throw new \Exception('Failed to perform document search: ' . $e->getMessage());
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Document search error: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Helper function to get document IDs already used in previous steps.
   */
  protected function getUsedDocumentIds(array $context): array {
    $used_ids = [];
    foreach ($context['results'] as $step_result) {
      if (isset($step_result['data'])) {
        $data = is_string($step_result['data']) ? json_decode($step_result['data'], TRUE) : $step_result['data'];
        if (isset($data['similar_documents'])) {
          foreach ($data['similar_documents'] as $doc) {
            if (isset($doc['document_id'])) {
              $used_ids[] = $doc['document_id'];
            }
          }
        }
      }
    }
    return array_unique($used_ids);
  }

  /**
   * Process placeholders in search input.
   */
  protected function processPlaceholders(string $input, array $context): string {
    $required_steps = $this->getRequiredSteps($this->configuration);
    foreach ($required_steps as $step_key) {
      if ($step_output = $context['results'][$step_key] ?? NULL) {
        $input = str_replace('{' . $step_key . '}', $step_output, $input);
      }
    }
    return $input;
  }
}
