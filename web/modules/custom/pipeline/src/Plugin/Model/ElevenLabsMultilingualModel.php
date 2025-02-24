<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for ElevenLabs TTS.
 *
 * @Model(
 *   id = "elevenlabs_multilingual",
 *   label = @Translation("ElevenLabs Multilingual"),
 *   service = "elevenlabs",
 *   model_name = "eleven_multilingual_v2"
 * )
 */
class ElevenLabsMultilingualModel extends ModelBase
{
  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string
  {
    return 'https://api.elevenlabs.io/v1/text-to-speech';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultParameters(): array
  {
    return [
      'stability' => 0.5,
      'similarity_boost' => 0.75,
      'style' => 0,
      'use_speaker_boost' => true,
      'voice_id' => '', // Will be configured per instance
    ];
  }
}
