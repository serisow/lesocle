<?php

namespace Drupal\poll\Plugin\QuestionType;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\poll\ConfigurableQuestionTypeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\poll\Utility\AjaxTriggerAnalyzer;
/**
 * Provides a 'Multiple Choice' question type.
 *
 * @QuestionType(
 *   id = "multiple_choice",
 *   label = @Translation("Multiple Choice Question"),
 *   description = @Translation("A question with multiple options.")
 * )
 */
class MultipleChoiceQuestion extends ConfigurableQuestionTypeBase {

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return [
      'options' => [
        'option_1' => ['text' => ''],
        'option_2' => ['text' => ''],
      ],
      'single_choice' => false,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }
    $options = $form_state->getValue(['data', 'options']) ?? $this->configuration['options'] ?? [];

    $form['single_choice'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Single choice only'),
      '#description' => $this->t('If checked, only one option can be selected (radio buttons).'),
      '#default_value' => $this->configuration['single_choice'],
    ];
    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Answer Options'),
      '#prefix' => '<div id="multiple-choice-options-wrapper">',
      '#suffix' => '</div>',
    ];

    // Ensure there are always at least two options
    while (count($options) < 2) {
      $key = 'option_' . (count($options) + 1);
      $options[$key] = ['text' => ''];
    }
    foreach ($options as $key => $option) {
      $key = is_numeric($key) ? 'option_'.$key : $key;
      $form['options'][$key] = $this->buildOptionElement($key, $option['text'], $form_state);
    }

    $form['add_option'] = [
      '#type' => 'button',
      '#value' => $this->t('Add another option'),
      '#name' => 'add_option',
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['add-option']],
    ];

    // Check if this is an existing question by looking for a UUID
    $isExistingQuestion = !empty($this->getUuid());

    if ($isExistingQuestion) {
      $form['add_option']['#ajax'] = [
        'callback' => [$this, 'updateOptionsAjax'],
        'wrapper' => 'multiple-choice-options-wrapper',
      ];
    } else {
      $form['add_option']['#attributes']['data-question-type'] = 'multiple_choice';
      $form['add_option']['#attributes']['data-poll-id'] = $form_state->getFormObject()->getPoll()->id();
    }
    return $form;
  }
  /**
   * Ajax callback to update options.
   */
  public function updateOptionsAjax(array &$form, FormStateInterface $form_state) {
    if ($this->isRemovingOption($form_state)) {
      return $this->removeOptionAjax($form, $form_state);
    } elseif ($this->isAddingOption($form_state)) {
      return $this->addOptionAjax($form, $form_state);
    }
    $updatedOptions = $this->buildOptionsElement($form, $form_state);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#multiple-choice-options-wrapper', $updatedOptions));
    return $response;
  }

  /**
   * Helper method to build the options element.
   */
  protected function buildOptionsElement(array &$form, FormStateInterface $form_state) {
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Answer Options'),
      '#prefix' => '<div id="multiple-choice-options-wrapper">',
      '#suffix' => '</div>',
    ];

    $options = $form_state->getValue(['data', 'options']) ?? $this->configuration['options'];

    foreach ($options as $key => $option) {
      $element[$key] = $this->buildOptionElement($key, $option['text'], $form_state);
    }

    return $element;
  }
  protected function buildOptionElement($key, $text, FormStateInterface $form_state) {
    $element = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['option-container'],
        'data-option-key' => $key,
      ],
    ];

    $element['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Option @num', ['@num' => substr($key, 7)]),
      '#title_display' => 'invisible',
      '#default_value' => $text,
      '#attributes' => ['class' => ['option-text']],
    ];

    $element['remove'] = [
      '#type' => 'button',
      '#value' => $this->t('Remove'),
      '#name' => 'remove_option_' . $key,
      '#ajax' => [
        'callback' => [$this, 'updateOptionsAjax'],
        'event' => 'click',
        'wrapper' => 'multiple-choice-options-wrapper',
      ],
      '#attributes' => [
        'class' => ['remove', 'trash-icon'],
        'data-option-key' => $key,
        'data-poll-id' => $form_state->getFormObject()->getPoll()->id(),
        'data-question-uuid' => $this->getUuid(),
        'title' => $this->t('Remove this option'),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }
    $options = $form_state->getValue(['data', 'options']);
    // If options is null, use the options from form_state
    if ($options === null) {
      $options = $form_state->get('options') ?: [];
    }

    $options = array_filter($options, function($option) {
      return !empty($option['text']);
    });

    parent::validateConfigurationForm($form, $form_state);

    if (count($options) < 2) {
      $form_state->setErrorByName('options', $this->t('You must provide at least two non-empty options.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalSubmitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $options = $form_state->getValue(['data', 'options']);
    $options = array_filter($options, function($option) {
      return !empty($option['text']);
    });
    $this->configuration['options'] = $options;
    $this->configuration['single_choice'] = $form_state->getValue(['data', 'single_choice']);
  }

  public function removeOptionAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $option_key = $triggering_element['#attributes']['data-option-key'];

    // Get the current poll entity
    $poll = $form_state->getFormObject()->getPoll();

    // Get the current question type (this instance)
    $question_type = $poll->getQuestionType($this->getUuid());

    // Get the current configuration
    $configuration = $question_type->getConfiguration();

    // Remove the option from the configuration
    unset($configuration['data']['options'][$option_key]);

    // Update the question type with the new configuration
    $question_type->setConfiguration($configuration);

    // Save the poll entity to persist the changes
    $poll->save();

    // Update the form state with the new options & Rebuild the form
    $form_state->setValue(['data', 'options'], $configuration['data']['options']);


    $form_state->disableRedirect();
    $form = $this->formBuilder->rebuildForm($form_state->getBuildInfo()['form_id'], $form_state, $form);


    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#multiple-choice-options-wrapper', $form['data']['options']));

    return $response;
  }

  public function addOptionAjax(array &$form, FormStateInterface $form_state) {
    $options = $form_state->getValue(['data', 'options']) ?? $this->configuration['options'] ?? [];
    // Dynamically compute the new key
    $maxKey = 0;
    foreach (array_keys($options) as $key) {
      $keyNumber = intval(substr($key, 7)); // Extract number from 'option_X'
      $maxKey = max($maxKey, $keyNumber);
    }
    $newKey = 'option_' . ($maxKey + 1);

    $options[$newKey] = ['text' => ''];

    $form_state->setValue(['data', 'options'], $options);
    $this->configuration['options'] = $options;

    $form_state->disableRedirect();
    $form = $this->formBuilder->rebuildForm($form_state->getBuildInfo()['form_id'], $form_state, $form);


    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#multiple-choice-options-wrapper', $form['data']['options']));

    return $response;
  }


  private function isAddingOption(FormStateInterface $form_state): bool {
    $trigger_info = AjaxTriggerAnalyzer::determineTriggeringElement($form_state);
    return $trigger_info && isset($trigger_info['type']) && $trigger_info['type'] == 'add_option';
  }
  private function isRemovingOption(FormStateInterface $form_state): bool {
    $trigger_info = AjaxTriggerAnalyzer::determineTriggeringElement($form_state);
    return $trigger_info && isset($trigger_info['type']) && $trigger_info['type'] == 'remove_option';
  }

  private function makeOptionKey($options = []) : string {
    if (empty($options)) {
      return '';
    }
    $maxKey = 0;
    foreach (array_keys($options) as $key) {
      $keyNumber = intval(substr($key, 7)); // Extract number from 'option_X'
      $maxKey = max($maxKey, $keyNumber);
    }
    return 'option_' . ($maxKey + 1);
  }

  public function getOptions(): array {
    return $this->configuration['options'] ?? [];
  }

  public function formatAnswer($answer): string {
    $options = $this->getOptions();
    if (is_array($answer)) {
      $answer = array_filter($answer, function ($value) {
        return !empty($value);
      });
      return implode(', ', array_map(function($key) use ($options) {
        return $options[$key]['text'] ?? '';
      }, $answer));
    }
    return $options[$answer]['text'] ?? '';
  }

  /**
   * Checks if two arrays contain the same elements, regardless of order.
   *
   * @param array $array1
   * @param array $array2
   * @return bool
   */
  private function arraysHaveSameElements(array $array1, array $array2): bool {
    if (count($array1) !== count($array2)) {
      return false;
    }

    // Sort both arrays
    sort($array1);
    sort($array2);

    // Compare the sorted arrays
    return $array1 === $array2;
  }

}
