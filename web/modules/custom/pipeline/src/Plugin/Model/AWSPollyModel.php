<?php
namespace Drupal\pipeline\Plugin\Model;

use Drupal\pipeline\Plugin\ModelBase;

/**
 * Provides a model plugin for AWS Polly TTS.
 *
 * @Model(
 *   id = "aws_polly",
 *   label = @Translation("AWS Polly"),
 *   service = "aws_polly",
 *   model_name = "aws_polly_standard"
 * )
 */
class AWSPollyModel extends ModelBase
{
  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string
  {
    // AWS Polly doesn't use a fixed endpoint as it depends on the region
    // This will be constructed in the service class
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultParameters(): array
  {
    return [
      'region' => 'us-west-2',
      'voice_id' => 'Joanna', // Default voice
      'output_format' => 'mp3',
      'sample_rate' => '22050',
      'engine' => 'standard', // can be 'standard' or 'neural'
    ];
  }
}
