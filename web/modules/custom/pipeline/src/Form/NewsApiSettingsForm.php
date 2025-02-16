<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class NewsApiSettingsForm extends ConfigFormBase
{
  protected function getEditableConfigNames()
  {
    return ['pipeline.news_api_settings'];
  }

  public function getFormId()
  {
    return 'pipeline_news_api_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('pipeline.news_api_settings');

    $form['news_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('News API Key'),
      '#default_value' => $config->get('news_api_key'),
      '#description' => $this->t('Enter your News API Key.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config('pipeline.news_api_settings')
      ->set('news_api_key', $form_state->getValue('news_api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
