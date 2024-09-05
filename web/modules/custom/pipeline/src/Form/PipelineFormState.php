<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\FormState;

class PipelineFormState extends FormState {
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
