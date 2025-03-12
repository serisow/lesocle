<?php
namespace Drupal\pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * Service for processing images received from Go service.
 */
class GoServiceImageProcessor {

  /**
   * The image download service.
   *
   * @var \Drupal\pipeline\Service\ImageDownloadService
   */
  protected $imageDownloadService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new GoServiceImageProcessor.
   *
   * @param \Drupal\pipeline\Service\ImageDownloadService $image_download_service
   *   The image download service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    ImageDownloadService $image_download_service,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->imageDownloadService = $image_download_service;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Process news items with images from Go service.
   *
   * @param string $data
   *   The JSON string of news items from Go service.
   *
   * @return string
   *   The JSON string of processed news items.
   */
  public function processNewsItemsWithImages(string $data): string {
    $news_items = json_decode($data, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($news_items)) {
      $this->loggerFactory->get('pipeline')->error('Invalid JSON data for news items');
      return $data;
    }

    $processed_items = [];

    foreach ($news_items as $item) {
      try {
        // Check if item has image_info with URL
        if (!empty($item['image_info']) && isset($item['image_info']['url'])) {
          // Download the image from the URL
          $image_url = $item['image_info']['url'];
          $file_info = $this->imageDownloadService->downloadImage($image_url);

          $file_info = json_decode($file_info, TRUE);
          if (json_last_error() !== JSON_ERROR_NONE || !is_array($file_info)) {
            $this->loggerFactory->get('pipeline')->error('Invalid JSON data for image file info');
            return $data;
          }

          // Replace image_info with the local file information
          $item['image_info'] = [
            'file_id' => $file_info['file_id'],
            'uri' => $file_info['uri'],
            'filename' => $file_info['filename'],
            'mime' => $file_info['mime'],
          ];
        }

        $processed_items[] = $item;
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('pipeline')->error('Error processing news item image: @error', [
          '@error' => $e->getMessage(),
        ]);

        // Keep the item but remove invalid image_info
        $item['image_info'] = null;
        $item['image_error'] = $e->getMessage();
        $processed_items[] = $item;
      }
    }

    return json_encode($processed_items);
  }
}
