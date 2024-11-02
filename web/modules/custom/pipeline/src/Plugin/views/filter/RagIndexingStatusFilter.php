<?php
namespace Drupal\pipeline\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter by RAG indexing status.
 *
 * @ViewsFilter("rag_indexing_status_filter")
 */
class RagIndexingStatusFilter extends InOperator
{

  /**
   * {@inheritdoc}
   */
  public function getValueOptions()
  {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [
        'pending' => $this->t('Pending'),
        'processing' => $this->t('Processing'),
        'completed' => $this->t('Completed'),
        'failed' => $this->t('Failed'),
      ];
    }
    return $this->valueOptions;
  }
}
