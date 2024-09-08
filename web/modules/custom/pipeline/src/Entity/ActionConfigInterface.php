<?php
namespace Drupal\pipeline\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Action Config entities.
 */
interface ActionConfigInterface extends ConfigEntityInterface {

  /**
   * Gets the Action type.
   *
   * @return string
   *   The Action type.
   */
  public function getActionType();

  /**
   * Sets the Action type.
   *
   * @param string $action_type
   *   The Action type.
   *
   * @return $this
   */
  public function setActionType($action_type);

  /**
   * Get the target entity type.
   *
   * @return string
   */
  public function getTargetEntityType();

  /**
   * Set the target entity type.
   *
   * @param string $target_entity_type
   *   The target entity type.
   *
   * @return $this
   */
  public function setTargetEntityType($target_entity_type);

  /**
   * Gets the Entity bundle.
   *
   * @return string
   *   The Entity bundle.
   */
  public function getEntityBundle();

  /**
   * Sets the Entity bundle.
   *
   * @param string $entity_bundle
   *   The Entity bundle.
   *
   * @return $this
   */
  public function setEntityBundle($entity_bundle);

  /**
   * Gets the API endpoint.
   *
   * @return string
   *   The API endpoint.
   */
  public function getApiEndpoint();

  /**
   * Sets the API endpoint.
   *
   * @param string $api_endpoint
   *   The API endpoint.
   *
   * @return $this
   */
  public function setApiEndpoint($api_endpoint);

}
