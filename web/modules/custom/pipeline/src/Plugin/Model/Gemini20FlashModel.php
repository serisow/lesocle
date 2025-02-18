<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for Gemini 2.0 Flash.
 *
 * @Model(
 *   id = "gemini_2_0_flash",
 *   label = @Translation("Gemini 2.0 Flash"),
 *   service = "gemini",
 *   model_name = "gemini-2.0-flash"
 * )
 */
class Gemini20FlashModel extends ModelBase
{

  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string
  {
    return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
  }

}
