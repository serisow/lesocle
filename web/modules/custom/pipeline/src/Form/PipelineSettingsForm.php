<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class PipelineSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['pipeline.settings'];
  }

  public function getFormId() {
    return 'pipeline_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pipeline.settings');
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('pipeline.settings')
      ->save();

    parent::submitForm($form, $form_state);
  }
}
