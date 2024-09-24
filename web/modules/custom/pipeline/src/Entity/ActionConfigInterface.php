<?php
namespace Drupal\pipeline\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

interface ActionConfigInterface extends ConfigEntityInterface {

  /**
   * Gets the Action service.
   *
   * @return string
   *   The Action service.
   */
  public function getActionService();

  /**
   * Sets the Action service.
   *
   * @param string $action_service
   *   The Action service.
   *
   * @return $this
   */
  public function setActionService($action_service);

  /**
   * Gets the configuration.
   *
   * @return array
   *   The configuration.
   */
  public function getConfiguration();

  /**
   * Sets the configuration.
   *
   * @param array $configuration
   *   The configuration.
   *
   * @return $this
   */
  public function setConfiguration(array $configuration);
}
