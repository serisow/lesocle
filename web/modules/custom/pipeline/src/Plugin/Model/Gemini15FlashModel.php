<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for Gemini 1.5 Flash.
 *
 * @Model(
 *   id = "gemini_1_5_flash",
 *   label = @Translation("Gemini 1.5 Flash"),
 *   service = "gemini",
 *   model_name = "gemini-1.5-flash"
 * )
 */
class Gemini15FlashModel extends ModelBase
{

  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string
  {
    return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
  }

}
