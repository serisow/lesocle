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
 *     "action_type",
 *     "entity_bundle",
 *     "target_entity_type",
 *     "api_endpoint",
 *   }
 * )
 */
class ActionConfig extends ConfigEntityBase  implements ActionConfigInterface
{
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
   * The Action type.
   *
   * @var string
   */
  protected $action_type;

  /**
   * The target entity type (for entity-related actions).
   *
   * @var string
   */
  protected $target_entity_type;

  /**
   * The Entity bundle (for entity-related actions).
   *
   * @var string
   */
  protected $entity_bundle;

  /**
   * The API endpoint (for external API calls).
   *
   * @var string
   */
  protected $api_endpoint;

  /**
   * {@inheritdoc}
   */
  public function getActionType() {
    return $this->action_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setActionType($action_type) {
    $this->action_type = $action_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  /**
   * Get the target entity type.
   *
   * @return string
   */
  public function getTargetEntityType() {
    return $this->target_entity_type;
  }
  /**
   * {@inheritdoc}
   */
  public function setTargetEntityType($target_entity_type) {
    $this->target_entity_type = $target_entity_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle() {
    return $this->entity_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityBundle($entity_bundle) {
    $this->entity_bundle = $entity_bundle;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiEndpoint() {
    return $this->api_endpoint;
  }

  /**
   * {@inheritdoc}
   */
  public function setApiEndpoint($api_endpoint) {
    $this->api_endpoint = $api_endpoint;
    return $this;
  }
}
