<?php

namespace Drupal\pipeline\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Prompt Template add/edit forms.
 */
class PromptTemplateForm extends EntityForm
{

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state)
  {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\pipeline\Entity\PromptTemplate $prompt_template */
    $prompt_template = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prompt Template Name'),
      '#maxlength' => 255,
      '#default_value' => $prompt_template->label(),
      '#description' => $this->t('Name of the prompt template.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $prompt_template->id(),
      '#machine_name' => [
        'exists' => '\Drupal\pipeline\Entity\PromptTemplate::load',
      ],
      '#disabled' => !$prompt_template->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $prompt_template->getDescription(),
      '#description' => $this->t('A brief description of the prompt template.'),
      '#rows' => 3,
    ];

    $form['template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Template'),
      '#default_value' => $prompt_template->getTemplate(),
      '#description' => $this->t('The prompt template content.'),
      '#required' => TRUE,
      '#rows' => 5,
    ];

    $form['output_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Output Format'),
      '#options' => [
        'json' => $this->t('JSON'),
        'html' => $this->t('HTML'),
        'text' => $this->t('Plain Text'),
      ],
      '#default_value' => $prompt_template->getOutputFormat(),
      '#description' => $this->t('The expected output format from the LLM.'),
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
    // Add custom validation if needed
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state)
  {
    $prompt_template = $this->entity;
    $status = $prompt_template->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label Prompt Template.', [
        '%label' => $prompt_template->label(),
      ]));
    } else {
      $this->messenger()->addMessage($this->t('The %label Prompt Template was not saved.', [
        '%label' => $prompt_template->label(),
      ]), 'error');
    }

    $form_state->setRedirectUrl($prompt_template->toUrl('collection'));
  }

}
