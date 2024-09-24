<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for Gemini 1.5 Pro.
 *
 * @Model(
 *   id = "gemini_1_5_pro_002",
 *   label = @Translation("Gemini 1.5 Pro 002"),
 *   service = "gemini",
 *   model_name = "gemini-1.5-pro-002"
 * )
 */
class Gemini15Pro002Model extends ModelBase
{

  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string
  {
    return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-002:generateContent';
  }

}
