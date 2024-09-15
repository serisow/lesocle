<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for Claude 3.5 Sonnet.
 *
 * @Model(
 *   id = "claude_3_5_sonnet",
 *   label = @Translation("Claude 3.5 Sonnet"),
 *   service = "anthropic",
 *   model_name = "claude-3-5-sonnet-20240620"
 * )
 */
class Claude35SonnetModel extends ModelBase {
  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string {
    return 'https://api.anthropic.com/v1/messages';
  }

}
