<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for Gemini 1.5 Pro.
 *
 * @Model(
 *   id = "gemini_1_5_pro",
 *   label = @Translation("Gemini 1.5 Pro"),
 *   service = "gemini",
 *   model_name = "gemini-1.5-pro-exp-0827"
 * )
 */
class Gemini15ProModel extends ModelBase
{

  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string
  {
    return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-exp-0827:generateContent';
  }

}
