<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles Social Media steps.
 */
class SocialMediaStepHandler implements StepHandlerInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new SocialMediaStepHandler.
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
    if (isset($configuration['article'])) {
      $string = trim($configuration['article'], '"');
      if (preg_match('/\((\d+)\)$/', $string, $matches)) {
        $nid = (int) $matches[1];
        /** @var \Drupal\node\Entity\Node $node */
        $node = $entity_type_manager->getStorage('node')->load($nid);
        if ($node) {
          // Add article data to step configuration
          $step_data['article_data'] = [
            'nid' => $node->id(),
            'title' => $node->getTitle(),
            'summary' => $node->hasField('field_summary') && !$node->get('field_summary')->isEmpty() ?
              $node->get('field_summary')->value :
              (!$node->get('body')->isEmpty() ? $node->get('body')->summary : $node->get('body')->value),
          ];

          // Add image URL if available
          if ($node->hasField('field_media_image') && !$node->get('field_media_image')->isEmpty()) {
            // Retrieve the media entity.
            $media = $node->get('field_media_image')->entity;
            if ($media && !$media->get('field_media_image')->isEmpty()) {
              // Retrieve the file entity from the media entity.
              $file = $media->get('field_media_image')->entity;
              if ($file) {
                // Get the file URI and generate the URL.
                /** @var \Drupal\file\FileInterface $file */
                $uri = $file->getFileUri();
                $url = \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
                $step_data['article_data']['image_url'] = $url;
              }
            }
          }

          // Retrieve the node URL (canonical URL) and set it.
          $node_url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
          $step_data['article_data']['url'] = $node_url;
        }
      }
    }
  }
}
