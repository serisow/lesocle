<?php

namespace Drupal\pipeline\Plugin\StepType;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\pipeline\AbstractLLMStepType;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an 'LLM' step type.
 *
 * @StepType(
 *   id = "llm_step",
 *   label = @Translation("LLM Step"),
 *   description = @Translation("A step to issue LLM API calls.")
 * )
 */
class LLMStep extends AbstractLLMStepType {
  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return [
      'prompt' => '',
      'response' => '',
      'prompt_template' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::additionalConfigurationForm($form, $form_state);

    $prompt_template_value = $this->configuration['prompt_template'] ?? '';
    // Extract the entity ID if it's in the format "Label (machine_name)"
    if (preg_match('/\((.*?)\)$/', $prompt_template_value, $matches)) {
      $prompt_template_value = $matches[1];
    }

    $form['prompt_template'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Prompt Template'),
      '#description' => $this->t('Select a pre-defined prompt template to quickly populate the prompt field. This helps maintain consistency and saves time. You have to edit the prompt field to adapt the variables from the Required Steps field.'),
      '#target_type' => 'prompt_template',
      '#default_value' => $prompt_template_value ? $this->entityTypeManager->getStorage('prompt_template')->load($prompt_template_value) : NULL,
    ];

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('Enter the prompt for the AI model. If you\'ve selected a template above, it will appear here and can be further customized. Use placeholders like {step_key} to incorporate results from previous steps. IMPORTANT: Each placeholder must be listed in the \'Required Steps\' field above. This ensures that the necessary data from previous steps is available when this step executes.'),
      '#default_value' => $this->configuration['prompt'],
      '#required' => TRUE,
      '#prefix' => '<div id="prompt-wrapper">',
      '#suffix' => '</div>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::additionalSubmitConfigurationForm($form, $form_state);
    $this->configuration['prompt_template'] = $form_state->getValue(['data', 'prompt_template']);
    $this->configuration['prompt'] = $form_state->getValue(['data', 'prompt']);
  }

  /**
   * Need to be overriden in LLMStep type.
   * {@inheritdoc}
   */
  public function getPrompt() : string {
    return $this->configuration['prompt'] ?? '';
  }


  /**
   * Ajax callback to update the prompt field.
   */
  public function updatePromptField(array &$form, FormStateInterface $form_state) {
    $prompt_template_id = $form_state->getValue(['data', 'prompt_template']);
    $response = new AjaxResponse();

    if ($prompt_template_id) {
      $prompt_template = $this->entityTypeManager->getStorage('prompt_template')->load($prompt_template_id);
      if ($prompt_template) {
        $form['data']['prompt']['#value'] = $prompt_template->getTemplate();
        $response->addCommand(new ReplaceCommand('#prompt-wrapper', $form['data']['prompt']));
      }
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string {
    return parent::execute($context);
  }

}
