<?php
namespace Drupal\pipeline\Service;

use Drupal\Core\Form\FormStateInterface;

/**
 * Manages file operations for pipeline step types.
 */
class PipelineFileManager {

  /**
   * File-based step types that use file uploads.
   *
   * @var array
   */
  protected $fileBasedStepTypes = [
    'upload_image_step',
    'upload_audio_step'
  ];

  /**
   * File field mappings configuration.
   *
   * @var array
   */
  protected $fileFields = [
    'image_file' => [
      'add_path' => 'data_image_file',
      'update_path' => ['data', 'image_file'],
      'config_key' => 'image_file_id',
    ],
    'audio_file' => [
      'add_path' => 'data_audio_file',
      'update_path' => ['data', 'audio_file'],
      'config_key' => 'audio_file_id',
    ],
  ];

  /**
   * Checks if the step type is a file-based step type.
   *
   * @param string $plugin_id
   *   The plugin ID of the step type.
   *
   * @return bool
   *   TRUE if the step type is file-based, FALSE otherwise.
   */
  public function isFileBasedStepType($plugin_id)
  {
    return in_array($plugin_id, $this->fileBasedStepTypes);
  }

  /**
   * Processes uploaded files in the request data.
   *
   * @param array $requestData
   *   The request data to process.
   * @param string $context
   *   The context, either 'add' or 'update'.
   *
   * @return array
   *   The processed request data.
   */
  public function processUploadedFiles(array &$requestData, string $context = 'add')
  {
    foreach ($this->fileFields as $fieldType => $paths) {
      // Try to locate file ID in either add or update path
      $fileId = null;

      // Check add path
      if (isset($requestData[$paths['add_path']]['fids']) && !empty($requestData[$paths['add_path']]['fids'])) {
        $fileId = $requestData[$paths['add_path']]['fids'];
      } // Check update path
      elseif ($this->getNestedValue($requestData, array_merge($paths['update_path'], ['fids']))) {
        $fileId = $this->getNestedValue($requestData, array_merge($paths['update_path'], ['fids']));
      }

      // If we found a valid file ID, store it in the standard location
      if ($fileId) {
        $requestData['data'][$paths['config_key']] = intval($fileId);
      }

      // Clean up to prevent serialization issues
      if (isset($requestData[$paths['add_path']])) {
        unset($requestData[$paths['add_path']]);
      }

      if ($this->getNestedValue($requestData, $paths['update_path'])) {
        $this->unsetNestedValue($requestData, $paths['update_path']);
      }
    }

    return $requestData;
  }

  /**
   * Handles file-based step types by standardizing file data.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $plugin_id
   *   The plugin ID of the step type.
   *
   * @return array
   *   Cleaned configuration values.
   */
  public function handleFileBasedStepType(FormStateInterface $form_state, $plugin_id)
  {
    $values = $form_state->getValues();

    if ($plugin_id === 'upload_image_step') {
      $image_file = $form_state->getValue(['data', 'image_file', 'fids']);
      if (!empty($image_file) && !empty($image_file[0])) {
        $values['data']['image_file_id'] = intval($image_file[0]);
        unset($values['data']['image_file']);
      }
    } elseif ($plugin_id === 'upload_audio_step') {
      $audio_file = $form_state->getValue(['data', 'audio_file', 'fids']);
      if (!empty($audio_file) && !empty($audio_file[0])) {
        $values['data']['audio_file_id'] = intval($audio_file[0]);
        unset($values['data']['audio_file']);
      }
    }
    return $values;
  }

  /**
   * Remove uploaded files from form state to prevent serialization errors.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeUploadedFilesFromFormState(FormStateInterface $form_state)
  {
    foreach ($this->fileFields as $fieldType => $paths) {
      if ($form_state->hasValue(['data', $fieldType])) {
        $form_state->unsetValue(['data', $fieldType]);
      }
    }
  }

  /**
   * Helper function to get a nested value from an array.
   */
  protected function getNestedValue(array $array, array $keys)
  {
    foreach ($keys as $key) {
      if (!isset($array[$key])) {
        return null;
      }
      $array = $array[$key];
    }
    return $array;
  }

  /**
   * Helper function to unset a nested value in an array.
   */
  protected function unsetNestedValue(array &$array, array $keys)
  {
    $last = array_pop($keys);

    foreach ($keys as $key) {
      if (!isset($array[$key]) || !is_array($array[$key])) {
        return;
      }
      $array = &$array[$key];
    }

    if (isset($array[$last])) {
      unset($array[$last]);
    }
  }
}
