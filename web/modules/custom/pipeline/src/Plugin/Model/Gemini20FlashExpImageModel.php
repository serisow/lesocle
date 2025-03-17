<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * @Model(
 *   id = "gemini_2_0_flash_exp_image",
 *   label = @Translation("Gemini 2.0 Flash Exp Image"),
 *   service = "gemini",
 *   model_name = "gemini-2.0-flash-exp-image"
 * )
 */
class Gemini20FlashExpImageModel extends ModelBase {
  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string {
    return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp-image-generation:generateContent';
  }
}
