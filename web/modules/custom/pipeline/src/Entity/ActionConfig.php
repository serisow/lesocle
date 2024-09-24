<?php
namespace Drupal\pipeline\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * @ConfigEntityType(
 *   id = "action_config",
 *   label = @Translation("Action Config"),
 *   handlers = {
 *     "list_builder" = "Drupal\pipeline\ActionConfigListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pipeline\Form\ActionConfigForm",
 *       "edit" = "Drupal\pipeline\Form\ActionConfigForm",
 *       "delete" = "Drupal\pipeline\Form\ActionConfigDeleteForm"
 *     },
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *   },
 *   config_prefix = "action_config",
 *   admin_permission = "administer action config",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "collection" = "/admin/config/action-config",
 *     "canonical" = "/admin/config/action-config/{action_config}",
 *     "add-form" = "/admin/config/action-config/add",
 *     "edit-form" = "/admin/config/action-config/{action_config}/edit",
 *     "delete-form" = "/admin/config/action-config/{action_config}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "action_service",
 *     "configuration",
 *   }
 * )
 */
class ActionConfig extends ConfigEntityBase  implements ActionConfigInterface {
  /**
   * The Action Config ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Action Config label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Action Service plugin ID.
   *
   * @var string
   */
  protected $action_service;

  /**
   * The Action Service configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * {@inheritdoc}
   */
  public function getActionService() {
    return $this->action_service;
  }

  /**
   * {@inheritdoc}
   */
  public function setActionService($action_service) {
    $this->action_service = $action_service;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
    return $this;
  }
}
