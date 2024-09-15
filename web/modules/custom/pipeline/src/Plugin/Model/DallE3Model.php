<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for DALL-E 3.
 *
 * @Model(
 *   id = "dall_e_3",
 *   label = @Translation("DALL-E 3"),
 *   service = "openai_image",
 *   model_name = "dall-e-3"
 * )
 */
class DallE3Model extends ModelBase {
  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string {
    return 'https://api.openai.com/v1/images/generations';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultParameters(): array {
    return [
      'temperature' => 0.7,
      'max_tokens' => 2000,
      'top_p' => 0.9,
      'frequency_penalty' => 0,
      'presence_penalty' => 0,
      'stop_sequence' => "\n",
    ];
  }

}
