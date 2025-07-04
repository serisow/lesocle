<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class GoogleSettingsForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['pipeline.google_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'pipeline_google_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('pipeline.google_settings');

    $form['google_custom_search_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Custom Search API Key'),
      '#default_value' => $config->get('google_custom_search_api_key'),
      '#description' => $this->t('Enter your Google Custom Search API Key.'),
      '#required' => TRUE,
    ];

    $form['google_custom_search_engine_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Custom Search Engine ID'),
      '#default_value' => $config->get('google_custom_search_engine_id'),
      '#description' => $this->t('Enter your Google Custom Search Engine ID.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config('pipeline.google_settings')
      ->set('google_custom_search_api_key', $form_state->getValue('google_custom_search_api_key'))
      ->set('google_custom_search_engine_id', $form_state->getValue('google_custom_search_engine_id'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
