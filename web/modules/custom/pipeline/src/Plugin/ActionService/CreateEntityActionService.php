<?php
namespace Drupal\pipeline\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
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
            $title = $title_matches[1] ?? 'Senegalese Hydrocarbons';

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

            $entity->save();
            return "Created new {$entity_type} entity of type {$bundle} with ID: " . $entity->id();
        } else {
            return "Cannot create the article, see log for details.";
        }

    }
}
