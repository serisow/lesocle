<?php
/**
 * Provides base implementation for configurable step types.
 *
 * This abstract class extends StepTypeBase to provide configuration management
 * capabilities for pipeline steps. It implements form handling, validation,
 * and configuration storage for configurable step types.
 *
 * Core functionalities:
 * - Form generation for step configuration
 * - Configuration validation and processing
 * - Default configuration management
 * - Step description and output handling
 *
 * Configuration features:
 * - Step description management
 * - Output key definition
 * - Required steps specification
 * - Output type selection
 *
 * Form handling:
 * - Builds configuration forms
 * - Manages AJAX updates
 * - Handles validation
 * - Processes submissions
 *
 * Important behaviors:
 * - Supports step dependencies through required steps
 * - Manages step output types
 * - Handles configuration inheritance
 * - Provides extension points for specific step types
 *
 * @see \Drupal\pipeline\StepTypeBase
 * @see \Drupal\pipeline\ConfigurableStepTypeInterface
 * @see \Drupal\pipeline\AbstractLLMStepType
 */

namespace Drupal\pipeline;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;

/**
 * Provides a base class for configurable step types.
 *
 * @see \Drupal\pipeline\Plugin\StepType\Annotation\StepType
 * @see \Drupal\pipeline\ConfigurableStepTypeInterface
 * @see \Drupal\pipeline\Plugin\StepTypeInterface
 * @see \Drupal\pipeline\StepTypeBase
 * @see \Drupal\pipeline\Plugin\StepTypeManager
 * @see plugin_api
 */
abstract class ConfigurableStepTypeBase extends StepTypeBase implements ConfigurableStepTypeInterface {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'step_description' => '',
        'step_output_key' => '',
        'output_type' => '',
        'required_steps' => '',
        'response' => ''
      ] + $this->additionalDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }

    $step_description = '';
    if ($form_state->has('step_description')) {
      $step_description = $form_state->get('step_description');
    } elseif (isset($this->configuration['step_description'])) {
      $step_description = $this->configuration['step_description'];
    }

    $form['step_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Step description'),
      '#default_value' => $step_description,
      '#description' => $this->t('Enter the text of the description.'),
      '#required' => TRUE,
      '#rows' => 2,
      '#weight' => -5
    ];

    $form['required_steps'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Required Steps'),
      '#description' => $this->t('List the output keys of previous steps that this step needs. Each key on a new line. These become available as {key} in your prompt.'),      '#default_value' => is_array($this->configuration['required_steps'])
        ? implode("\r\n", $this->configuration['required_steps'])
        : $this->configuration['required_steps'],
      '#required' => FALSE,
      '#weight' => 0
    ];

    $form['step_output_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Output Key'),
      '#description' => $this->t('Give this step\'s output a unique name. Other steps can use this name to access the result of this step.'),
      '#default_value' => $this->configuration['step_output_key'],
      '#required' => FALSE,
      '#weight' => 1
    ];

    $form['output_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Output Type'),
      '#description' => $this->t('Specify the type of content this step will produce. This helps other steps and actions identify and use this output correctly.
       <strong>Generic Content is the default and suitable for most steps, especially those producing intermediate results.</strong>'),
      '#options' => $this->getOutputTypeOptions(),
      '#default_value' => $this->configuration['output_type'] ?? 'generic_content',
      '#required' => TRUE,
      '#weight' => 2,
    ];

    return $this->additionalConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state){}


  /**
   * {@inheritdoc}
   */


  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['step_description'] = $form_state->getValue('step_description');
    $this->configuration['step_output_key'] = $form_state->getValue('step_output_key');
    $this->configuration['output_type'] = $form_state->getValue('output_type');
    $required_steps = $form_state->getValue(['data', 'required_steps']);
    $this->configuration['required_steps'] = array_filter(explode("\r\n", $required_steps));
    $this->additionalSubmitConfigurationForm($form, $form_state);
  }

  /**
   * Provides additional default configuration for the step type.
   *
   * @return array
   *   An associative array with additional default configuration.
   */
  protected function additionalDefaultConfiguration() {
    return [];
  }

  /**
   * Builds additional configuration form elements for the step type.
   *
   * @param array $form
   *   The form array to add to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The modified form array.
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Submits additional configuration form elements for the step type.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function additionalSubmitConfigurationForm(array &$form, FormStateInterface $form_state) {}

  public function getRequiredSteps(array $config) {
    return array_filter(explode("\r\n", $config['required_steps']));
  }


  protected function getOutputTypeOptions() {
    return [
      // Generic content do not have particular handling, it purpose is to be put in the context
      // in order to accessed by later steps.
      'generic_content'  => $this->t('Generic Content'),
      'article_content'  => $this->t('Article Content'),
      'featured_image'   => $this->t('Featured Image'),
      'seo_metadata'     => $this->t('SEO Metadata'),
      'taxonomy_term'    => $this->t('Taxonomy Term'),
      'tweet_content'    => $this->t('Tweet Content'), // used for posting tweet
      'twitter_search_results' => $this->t('Twitter Search Results'), // used for twitter search results
      'crisis_analysis_results' => $this->t('Twitter Crisis Analysis Results'),
      'sms_content'      => $this->t('Sms Content'),
      'bulk_sms_content' => $this->t('Bulk SMS Content'),
      'generic_webhook_content'  => $this->t('Generic Webhook Content'),
      'linkedin_content'  => $this->t('LinkedIn Content'),
    ];
  }
}
