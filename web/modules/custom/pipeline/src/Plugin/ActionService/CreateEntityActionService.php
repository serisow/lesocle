<?php
namespace Drupal\pipeline\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\file\FileRepositoryInterface;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "create_entity",
 *   label = @Translation("Create Entity Action")
 * )
 */
class CreateEntityActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  protected $fileSystem;
  protected $fileRepository;
  /**
   * Constructs a CreateEntityActionService object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    FileRepositoryInterface $file_repository
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('file.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
    public function executeAction(array $config, array &$context): string {
        $entity_type = $config['target_entity_type'];
        $bundle = $config['entity_bundle'];
        $results = $context['results'] ?? [];
        if (!empty($results)) {
          $storage = $this->entityTypeManager->getStorage($entity_type);
           $final_result =  end($results);
            // Extract title and body from the final result
            preg_match('/<h1>(.*?)<\/h1>/s', $final_result, $title_matches);
            $title = $title_matches[1] ?? 'TITLE GO HERE';

            // Remove the title from the body
            $body = preg_replace('/<h1>.*?<\/h1>/s', '', $final_result, 1);
            $entity = $storage->create([
                'type' => $bundle,
                'title' =>  $title,
                'body' => [
                    'value' => $body,
                    'format' => 'full_html',
                ],
                // Add other necessary fields based on the entity type and bundle
            ]);

            // Handle image if present
            if (isset($context['image_data'])) {
              $file = $this->saveImageAsFile($context['image_data']);
              $media = $this->createMediaEntity($file);
              $entity->set('field_media_image', $media);
            }
            $entity->save();
            return "Created new {$entity_type} entity of type {$bundle} with ID: " . $entity->id();
        } else {
            return "Cannot create the article, see log for details.";
        }

    }

    private function saveImageAsFile($image_data) {
      $directory = 'public://generated_images';

      // Ensure the directory exists
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      // Generate a unique filename
      $filename = 'generated_image_' . uniqid() . '.jpg';
      $uri = $directory . '/' . $filename;

      // Decode base64 image data if necessary
      if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type)) {
        $image_data = substr($image_data, strpos($image_data, ',') + 1);
        $image_data = base64_decode($image_data);

        if ($image_data === false) {
          throw new \Exception('Failed to decode image data');
        }
      } elseif (filter_var($image_data, FILTER_VALIDATE_URL)) {
        // If it's a URL, download the image
        $image_data = file_get_contents($image_data);
        if ($image_data === false) {
          throw new \Exception('Failed to download image from URL');
        }
      }

      // Save the file using the modern method
      try {
        $file = $this->fileRepository->writeData($image_data, $uri, FileSystemInterface::EXISTS_REPLACE);
      } catch (\Exception $e) {
        throw new \Exception('Failed to save image file: ' . $e->getMessage());
      }

      return $file;
    }
    private function createMediaEntity($file) {
      $media_storage = $this->entityTypeManager->getStorage('media');

      // Create the media entity
      $media = $media_storage->create([
        'bundle' => 'image',
        'uid' => \Drupal::currentUser()->id(),
        'status' => 1,
        'field_media_image' => [
          'target_id' => $file->id(),
          'alt' => 'Generated image',
          'title' => 'Generated image',
        ],
      ]);

      // Save the media entity
      $media->save();

      if (!$media->id()) {
        throw new \Exception('Failed to create media entity');
      }

      return $media;
    }
}
