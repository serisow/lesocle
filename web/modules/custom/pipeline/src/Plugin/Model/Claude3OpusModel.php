<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for Claude 3 Opus.
 *
 * @Model(
 *   id = "claude_3_opus",
 *   label = @Translation("Claude 3 Opus"),
 *   service = "anthropic",
 *   model_name = "claude-3-opus-20240229"
 * )
 */
class Claude3OpusModel extends ModelBase {
  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string {
    return 'https://api.anthropic.com/v1/messages';
  }

}
