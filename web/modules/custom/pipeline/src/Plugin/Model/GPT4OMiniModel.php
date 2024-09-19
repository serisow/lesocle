<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for GPT-4O MINI.
 *
 * @Model(
 *   id = "gpt_4_mini",
 *   label = @Translation("GPT-4O MINI "),
 *   service = "openai",
 *   model_name = "gpt-4o-mini"
 * )
 */
class GPT4OMiniModel extends ModelBase {
  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string {
    return 'https://api.openai.com/v1/chat/completions';
  }

}
