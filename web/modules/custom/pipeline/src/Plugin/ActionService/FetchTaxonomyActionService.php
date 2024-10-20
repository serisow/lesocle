<?php
namespace Drupal\pipeline\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "fetch_taxonomy_action",
 *   label = @Translation("Fetch Taxonomy Action")
 * )
 */
class FetchTaxonomyActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface
{
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a FetchTaxonomyActionService object.
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration)
  {
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $options = [];
    foreach ($vocabularies as $vid => $vocabulary) {
      $options[$vid] = $vocabulary->label();
    }

    $form['vocabularies'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies'),
      '#options' => $options,
      '#default_value' => $configuration['vocabularies'] ?? [],
      '#description' => $this->t('Select the vocabularies to fetch terms from.'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    return [
      'vocabularies' => array_filter($form_state->getValue('vocabularies')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string
  {
    $vocabularies = $config['configuration']['vocabularies'] ?? [];

    if (empty($vocabularies)) {
      throw new \Exception("No vocabularies selected for fetching terms.");
    }

    $result = [];
    foreach ($vocabularies as $vid) {
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid);
      foreach ($terms as $term) {
        $result[] = [
          'vid' => $vid,
          'tid' => $term->tid,
          'name' => $term->name,
          'description' => isset($term->description) ? $term->description : '',
          'weight' => $term->weight,
          'depth' => $term->depth,
          'parents' => $term->parents,
        ];
      }
    }

    return json_encode($result);
  }
}
