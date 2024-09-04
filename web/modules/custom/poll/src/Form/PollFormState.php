<?php
namespace Drupal\poll\Form;

use Drupal\Core\Form\FormState;

class PollFormState extends FormState {
  protected $skipResponse = false;

  public function setSkipResponse($skip = true) {
    $this->skipResponse = $skip;
    return $this;
  }

  public function isSkipResponse() {
    return $this->skipResponse;
  }

  public function getResponse() {
    if ($this->isSkipResponse()) {
      return null;
    }
    return parent::getResponse();
  }
}
