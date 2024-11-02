<?php

namespace Drupal\pipeline\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler for RAG indexing status.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("field_rag_indexing_status")
 */
class RagIndexingStatus extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    // Map status to colors and labels
    $status_map = [
      'pending' => [
        'class' => 'rag-status--pending',
        'color' => '#ffd700', // Yellow
        'label' => $this->t('Pending'),
      ],
      'processing' => [
        'class' => 'rag-status--processing',
        'color' => '#1e90ff', // Blue
        'label' => $this->t('Processing'),
      ],
      'completed' => [
        'class' => 'rag-status--completed',
        'color' => '#32cd32', // Green
        'label' => $this->t('Completed'),
      ],
      'failed' => [
        'class' => 'rag-status--failed',
        'color' => '#dc3545', // Red
        'label' => $this->t('Failed'),
      ],
    ];

    // Default to pending if status not found
    $status_info = $status_map[$value] ?? $status_map['pending'];

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['rag-status', $status_info['class']],
      ],
      '#value' => $status_info['label'],
      '#attached' => [
        'library' => ['pipeline/rag-status'],
      ],
    ];
  }
}
