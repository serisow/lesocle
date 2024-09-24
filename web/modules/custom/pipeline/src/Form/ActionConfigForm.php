<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\pipeline\Plugin\ActionServiceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
class ActionConfigForm extends EntityForm {
  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The action service manager.
   *
   * @var \Drupal\pipeline\Plugin\ActionServiceManager
   */
  protected $actionServiceManager;

  /**
   * Constructs a new ActionConfigForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\pipeline\Plugin\ActionServiceManager $action_service_manager
   *    The action service manager.
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_type_bundle_info, ActionServiceManager $action_service_manager) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->actionServiceManager = $action_service_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.action_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $action_config = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $action_config->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $action_config->id(),
      '#machine_name' => [
        'exists' => '\Drupal\pipeline\Entity\ActionConfig::load',
      ],
      '#disabled' => !$action_config->isNew(),
    ];

    $form['action_service'] = [
      '#type' => 'select',
      '#title' => $this->t('Action Service'),
      '#options' => $this->getActionServiceOptions(),
      '#default_value' => $action_config->getActionService(),
      '#required' => TRUE,
    ];


    $form['action_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Action Type'),
      '#options' => [
        'create_entity' => $this->t('Create Entity'),
        'update_entity' => $this->t('Update Entity'),
        'delete_entity' => $this->t('Delete Entity'),
        'call_api' => $this->t('Call External API'),
      ],
      '#default_value' => $action_config->getActionType(),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateActionTypeFields',
        'wrapper' => 'action-type-fields',
      ],
    ];

    $form['action_type_fields'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'action-type-fields'],
    ];

    $action_type = $form_state->getValue('action_type') ?: $action_config->getActionType();

    if (in_array($action_type, ['create_entity', 'update_entity', 'delete_entity'])) {
      $form['action_type_fields']['entity_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity Type'),
        '#options' => $this->getEntityTypeOptions(),
        '#default_value' => $action_config->getTargetEntityType(),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::updateEntityBundleOptions',
          'wrapper' => 'entity-bundle-wrapper',
        ],
      ];

      $entity_bundle = $action_config->getEntityBundle();
      $bundle_options = $this->getEntityBundleOptions($form_state);

      $form['action_type_fields']['entity_bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity Bundle'),
        '#options' => $this->getEntityBundleOptions($form_state),
        '#default_value' => $action_config->getEntityBundle(),
        '#required' => TRUE,
        '#prefix' => '<div id="entity-bundle-wrapper">',
        '#suffix' => '</div>',
      ];
    }
    elseif ($action_type == 'call_api') {
      $form['action_type_fields']['api_endpoint'] = [
        '#type' => 'url',
        '#title' => $this->t('API Endpoint'),
        '#default_value' => $action_config->getApiEndpoint(),
        '#required' => TRUE,
      ];
    }

    return $form;
  }

  /**
   * Ajax callback to update action type specific fields.
   */
  public function updateActionTypeFields(array &$form, FormStateInterface $form_state) {
    return $form['action_type_fields'];
  }

  /**
   * Ajax callback to update entity bundle options.
   */
  public function updateEntityBundleOptions(array &$form, FormStateInterface $form_state) {
    return $form['action_type_fields']['entity_bundle'];
  }

  /**
   * Get available entity type options.
   */
  protected function getEntityTypeOptions() {
    $options = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $options[$entity_type_id] = $entity_type->getLabel();
    }
    return $options;
  }

  /**
   * Get available entity bundle options.
   */
  protected function getEntityBundleOptions(FormStateInterface $form_state) {
    $options = [];
    $entity_type = $form_state->getValue(['action_type_fields', 'entity_type']);

    // If entity_type is not in form_state, use the stored value
    if (!$entity_type) {
      $entity_type = $this->entity->getTargetEntityType();
    }

    if ($entity_type) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
      foreach ($bundles as $bundle_id => $bundle_info) {
        $options[$bundle_id] = $bundle_info['label'];
      }
    }
    return $options;
  }

  /**
   * Get available action service options.
   */
  protected function getActionServiceOptions() {
    $options = [];
    foreach ($this->actionServiceManager->getDefinitions() as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $action_config = $this->entity;
    $status = $action_config->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Action Configuration.', [
          '%label' => $action_config->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Action Configuration.', [
          '%label' => $action_config->label(),
        ]));
    }
    $form_state->setRedirectUrl($action_config->toUrl('collection'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->entity->setTargetEntityType($form_state->getValue('entity_type'));
    $this->entity->setEntityBundle($form_state->getValue('entity_bundle'));
    $this->entity->setActionService($form_state->getValue('action_service'));
  }
}
