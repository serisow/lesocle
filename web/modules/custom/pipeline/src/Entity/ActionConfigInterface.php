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
   * Gets the execution location.
   *
   * @return string
   *   The execution location.
   */
  public function getExecutionLocation();

  /**
   * Sets the execution location.
   *
   * @param string $execution_location
   *   The execution location.
   *
   * @return $this
   */
  public function setExecutionLocation($execution_location);


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
