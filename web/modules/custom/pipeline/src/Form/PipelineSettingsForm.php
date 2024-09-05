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

    $form['frontend_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Frontend Base URL'),
      '#default_value' => $config->get('frontend_base_url'),
      '#description' => $this->t('The base URL of the frontend React application (e.g., http://localhost:3000).'),
      '#required' => TRUE,
    ];

    $form['openai_api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('OpenAI API URL'),
      '#default_value' => $config->get('openai_api_url'),
      '#description' => $this->t('The OpenAI API URL.'),
      '#required' => TRUE,
    ];


    $form['openai_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Open API Key'),
      '#default_value' => $config->get('openai_api_key'),
      '#description' => $this->t('The Open API Key from your OpenAPI account.'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('pipeline.settings')
      ->set('frontend_base_url', $form_state->getValue('frontend_base_url'))
      ->set('openai_api_url', $form_state->getValue('openai_api_url'))
      ->set('openai_api_key', $form_state->getValue('openai_api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
