<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class PipelineSettingsForm extends ConfigFormBase
{
  protected function getEditableConfigNames()
  {
    return ['pipeline.settings'];
  }

  public function getFormId()
  {
    return 'pipeline_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('pipeline.settings');

    $form['go_service_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Go Service URL'),
      '#default_value' => $config->get('go_service_url'),
      '#description' => $this->t('The base URL of the Go service (e.g., http://lesoclego-dev.sa)'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config('pipeline.settings')
      ->set('go_service_url', $form_state->getValue('go_service_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
