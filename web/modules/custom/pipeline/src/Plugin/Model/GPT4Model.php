<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for GPT-4.
 *
 * @Model(
 *   id = "gpt_4",
 *   label = @Translation("GPT-4"),
 *   service = "openai",
 *   model_name = "gpt-4"
 * )
 */
class GPT4Model extends ModelBase {
  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string {
    return 'https://api.openai.com/v1/chat/completions';
  }

}
