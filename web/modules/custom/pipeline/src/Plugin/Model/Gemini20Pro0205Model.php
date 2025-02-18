<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for Gemini 2.0 Pro.
 *
 * @Model(
 *   id = "gemini_2_0_pro_0205",
 *   label = @Translation("Gemini 2.0 Pro 0205"),
 *   service = "gemini",
 *   model_name = "gemini-2.0-pro-0205"
 * )
 */
class Gemini20Pro0205Model extends ModelBase
{

  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string
  {
    return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-pro-exp-02-05:generateContent';
  }

}
