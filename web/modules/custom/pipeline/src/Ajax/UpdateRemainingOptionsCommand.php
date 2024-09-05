<?php
namespace Drupal\pipeline\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Defines an Ajax command that calls a custom JavaScript function.
 */
class UpdateRemainingOptionsCommand implements CommandInterface {
  protected $removedOptionKey;

  public function __construct($removedOptionKey) {
    $this->removedOptionKey = $removedOptionKey;
  }

  public function render() {
    return [
      'command' => 'updateRemainingOptions',
      'removedOptionKey' => $this->removedOptionKey,
    ];
  }
}
