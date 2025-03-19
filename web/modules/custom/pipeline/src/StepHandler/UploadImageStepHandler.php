<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Handles Upload Image steps.
 */
class UploadImageStepHandler implements StepHandlerInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a new UploadImageStepHandler.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    FileUrlGeneratorInterface $file_url_generator
  ) {
    $this->logger = $logger_factory->get('pipeline');
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function processStepData(array &$step_data, array $configuration, EntityTypeManagerInterface $entity_type_manager) {
    // Add image file information if available
    if (!empty($configuration['image_file_id'])) {
      $file_id = $configuration['image_file_id'];
      /** @var \Drupal\file\FileInterface $file */
      $file = $entity_type_manager->getStorage('file')->load($file_id);
      if ($file) {
        $step_data['upload_image_config']['image_file_id'] = $file_id;
        $step_data['upload_image_config']['image_file_url'] = $file->createFileUrl(FALSE);
        $step_data['upload_image_config']['image_file_uri'] = $file->getFileUri();
        $step_data['upload_image_config']['image_file_mime'] = $file->getMimeType();
        $step_data['upload_image_config']['image_file_name'] = $file->getFilename();
        $step_data['upload_image_config']['image_file_size'] = $file->getSize();
        $step_data['upload_image_config']['duration'] = (float) $configuration['video_settings']['duration'];
        if (!empty($configuration['text_blocks'])) {
          $step_data['upload_image_config']['text_blocks'] = $configuration['text_blocks'];
        }
      }
    }
  }
}
