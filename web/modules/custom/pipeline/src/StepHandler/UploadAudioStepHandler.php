<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles Upload Audio steps.
 */
class UploadAudioStepHandler implements StepHandlerInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new UploadAudioStepHandler.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('pipeline');
  }

  /**
   * {@inheritdoc}
   */
  public function processStepData(array &$step_data, array $configuration, EntityTypeManagerInterface $entity_type_manager) {
    // Add audio file information if available
    if (!empty($configuration['audio_file_id'])) {
      $file_id = $configuration['audio_file_id'];
      /** @var \Drupal\file\FileInterface $file */
      $file = $entity_type_manager->getStorage('file')->load($file_id);
      if ($file) {
        $step_data['upload_audio_config']['audio_file_id'] = $file_id;
        $step_data['upload_audio_config']['audio_file_url'] = $file->createFileUrl(FALSE);
        $step_data['upload_audio_config']['audio_file_uri'] = $file->getFileUri();
        $step_data['upload_audio_config']['audio_file_mime'] = $file->getMimeType();
        $step_data['upload_audio_config']['audio_file_name'] = $file->getFilename();
        $step_data['upload_audio_config']['audio_file_duration'] = $this->getAudioDuration($file);
        $step_data['upload_audio_config']['audio_file_size'] = $file->getSize();
      }
    }
  }

  /**
   * Gets the duration of an audio file in seconds.
   *
   * @param \Drupal\file\FileInterface $file
   *   The audio file.
   *
   * @return float|null
   *   The duration in seconds, or NULL if it couldn't be determined.
   */
  protected function getAudioDuration($file) {
    // Use ffprobe if available
    $real_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    if (function_exists('exec') && is_executable('/usr/bin/ffprobe')) {
      $command = "/usr/bin/ffprobe -i " . escapeshellarg($real_path) . " -show_entries format=duration -v quiet -of csv=\"p=0\"";
      $output = [];
      exec($command, $output, $return_var);

      if ($return_var === 0 && !empty($output[0]) && is_numeric($output[0])) {
        return (float) $output[0];
      }
    }

    // Fallback: Return null if we can't determine duration
    return null;
  }
}
