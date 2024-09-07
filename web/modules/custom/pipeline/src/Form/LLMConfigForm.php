<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for LLM Config add/edit forms.
 */
class LLMConfigForm extends EntityForm
{

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state)
  {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\pipeline\Entity\LLMConfig $llm_config */
    $llm_config = $this->entity;

    // Name field for the LLM Config entity.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('LLM Config Name'),
      '#maxlength' => 255,
      '#default_value' => $llm_config->label(),
      '#description' => $this->t('The name of the LLM configuration.'),
      '#required' => TRUE,
    ];

    // Machine name field (used internally).
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $llm_config->id(),
      '#machine_name' => [
        'exists' => '\Drupal\pipeline\Entity\LLMConfig::load',
      ],
      '#disabled' => !$llm_config->isNew(),
    ];

    // API URL field.
    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API URL'),
      '#maxlength' => 255,
      '#default_value' => $llm_config->getApiUrl(),
      '#description' => $this->t('The URL of the API endpoint.'),
      '#required' => TRUE,
    ];

    // API Key field.
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#maxlength' => 255,
      '#default_value' => $llm_config->getApiKey(),
      '#description' => $this->t('The API key used for authentication.'),
      '#required' => TRUE,
    ];

    // Model Name field.
    $form['model_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Model Name'),
      '#options' => [
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'gpt-4' => 'GPT-4',
        'gemini-1.5-flash' => 'GEMINI-1.5 Flash',
        'claude-3-5-sonnet-20240620' => 'Claude-3-5-sonnet'
        // Add more options as needed
      ],
      '#default_value' => $llm_config->get('model_name'),
      '#required' => TRUE,
    ];

    // Model Version field.
    $form['model_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Model Version'),
      '#maxlength' => 255,
      '#default_value' => $llm_config->getModelVersion(),
      '#description' => $this->t('The version of the LLM model.'),
      '#required' => TRUE,
    ];

    // Temperature field.
    $form['temperature'] = [
      '#type' => 'number',
      '#title' => $this->t('Temperature'),
      '#default_value' => $llm_config->getTemperature(),
      '#description' => $this->t('Controls the randomness of the output.'),
      '#min' => 0.0,
      '#max' => 1.0,
      '#step' => 0.01,
      '#required' => TRUE,
    ];

    // Max Tokens field.
    $form['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#default_value' => $llm_config->getMaxTokens(),
      '#description' => $this->t('The maximum number of tokens to generate.'),
      '#min' => 1,
      '#required' => TRUE,
    ];

    // Top-P field.
    $form['top_p'] = [
      '#type' => 'number',
      '#title' => $this->t('Top-P'),
      '#default_value' => $llm_config->getTopP(),
      '#description' => $this->t('Controls the diversity of the response.'),
      '#min' => 0.0,
      '#max' => 1.0,
      '#step' => 0.01,
      '#required' => TRUE,
    ];

    // Frequency Penalty field.
    $form['frequency_penalty'] = [
      '#type' => 'number',
      '#title' => $this->t('Frequency Penalty'),
      '#default_value' => $llm_config->getFrequencyPenalty(),
      '#description' => $this->t('Penalizes frequent word usage.'),
      '#min' => 0.0,
      '#max' => 2.0,
      '#step' => 0.01,
      '#required' => TRUE,
    ];

    // Presence Penalty field.
    $form['presence_penalty'] = [
      '#type' => 'number',
      '#title' => $this->t('Presence Penalty'),
      '#default_value' => $llm_config->getPresencePenalty(),
      '#description' => $this->t('Penalizes word reuse.'),
      '#min' => 0.0,
      '#max' => 2.0,
      '#step' => 0.01,
      '#required' => TRUE,
    ];

    // Stop Sequence field.
    $form['stop_sequence'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stop Sequence'),
      '#default_value' => $llm_config->getStopSequence(),
      '#description' => $this->t('The sequence that stops the output.'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
    // Custom validation logic can go here if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state)
  {
    $llm_config = $this->entity;
    $status = $llm_config->save();

    if ($status == SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Created new LLM Config %label.', ['%label' => $llm_config->label()]));
    } else {
      $this->messenger()->addMessage($this->t('Updated LLM Config %label.', ['%label' => $llm_config->label()]));
    }

    $form_state->setRedirectUrl($llm_config->toUrl('collection'));
  }

}

