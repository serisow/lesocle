<?php
namespace Drupal\poll\Utility;

use Drupal\Core\Form\FormStateInterface;

class AjaxTriggerAnalyzer
{

  /**
   * Determines the triggering element for an AJAX request.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array|null
   *   An array containing information about the triggering element, or null if not found.
   */
  public static function determineTriggeringElement(FormStateInterface $form_state)
  {
    $input = $form_state->getUserInput();

    // Check if we have triggering element information in the input
    if (isset($input['_triggering_element_name']) && isset($input['_triggering_element_value'])) {
      return [
        'name' => $input['_triggering_element_name'],
        'value' => $input['_triggering_element_value'],
        'type' => self::determineTriggerType($input),
      ];
    }

    // If not found in input, check the triggering_element property of form_state
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element) {
      return [
        'name' => $triggering_element['#name'] ?? null,
        'value' => $triggering_element['#value'] ?? null,
        'type' => $triggering_element['#type'] ?? null,
      ];
    }

    return null;
  }

  /**
   * Determines the type of the triggering element.
   *
   * @param array $input
   *   The user input array from form state.
   *
   * @return string|null
   *   The type of the triggering element, or null if not determinable.
   */
  private static function determineTriggerType(array $input)
  {
    // Check for common button types
    if (strpos($input['_triggering_element_name'], 'remove_option_') === 0) {
      return 'remove_option';
    }
    if ($input['_triggering_element_name'] === 'add_option') {
      return 'add_option';
    }
    // Add more type determinations as needed

    return null;
  }
}
