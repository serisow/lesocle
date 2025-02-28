<?php

namespace Drupal\pipeline\Plugin\StepType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an audio upload step type.
 *
 * @StepType(
 *   id = "upload_audio_step",
 *   label = @Translation("Upload Audio Step"),
 *   description = @Translation("Upload an audio file to be used in the pipeline.")
 * )
 */
class UploadAudioStep extends ConfigurableStepTypeBase implements StepTypeExecutableInterface
{

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration()
  {
    return [
      'audio_file_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::additionalConfigurationForm($form, $form_state);

    // Ensure form doesn't use caching because of the file field
    $form_state->disableCache();

    // Audio Upload field
    $form['audio_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Audio'),
      '#description' => $this->t('Upload an audio file (MP3, WAV, OGG). Maximum size: 2MB.'),
      '#default_value' => $this->configuration['audio_file_id'] ? [$this->configuration['audio_file_id']] : NULL,
      '#upload_location' => 'public://pipeline/uploads/audio',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'mp3 wav ogg'],
        'FileSizeLimit' => ['fileLimit' => 50 * 1024 * 1024],
      ],
      '#required' => TRUE,
      '#process' => [
        // Remove Drupal's default AJAX callbacks
        [get_class($this), 'processManagedFile'],
      ],
    ];

    return $form;
  }

  /**
   * Process callback for managed file to remove default AJAX behavior.
   */
  public static function processManagedFile($element, FormStateInterface $form_state, &$complete_form) {
    // Use Drupal's default process but remove the AJAX settings
    $element = \Drupal\file\Element\ManagedFile::processManagedFile($element, $form_state, $complete_form);
    unset($element['upload']['#ajax']);
    unset($element['remove_button']['#ajax']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    parent::submitConfigurationForm($form, $form_state);

    // Handle file upload
    $audio_file = $form_state->getValue(['data', 'audio_file']);
    if (!empty($audio_file) && !empty($audio_file[0])) {
      $this->configuration['audio_file_id'] = $audio_file[0];

      // Make file permanent
      $file = $this->entityTypeManager->getStorage('file')->load($this->configuration['audio_file_id']);
      if ($file instanceof FileInterface) {
        $file->setPermanent();
        $file->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string
  {
    try {
      // Validate file exists
      if (empty($this->configuration['audio_file_id'])) {
        throw new \Exception('No audio file has been uploaded.');
      }

      // Load the file
      $file = $this->entityTypeManager->getStorage('file')->load($this->configuration['audio_file_id']);
      if (!$file instanceof FileInterface) {
        throw new \Exception('Invalid audio file: File not found.');
      }

      // Create the result in the same format as the LLM audio generation
      $result = [
        'file_id' => $file->id(),
        'uri' => $file->getFileUri(),
        'url' => $file->createFileUrl(FALSE),
        'mime_type' => $file->getMimeType(),
        'filename' => $file->getFilename(),
        'size' => $file->getSize(),
        'timestamp' => \Drupal::time()->getCurrentTime(),
      ];

      // Add the result to the context with the appropriate output type
      $context['results'][$this->getStepOutputKey()] = [
        'output_type' => 'audio_content',
        'data' => json_encode($result),
      ];

      return json_encode($result);
    } catch (\Exception $e) {
      throw new \Exception('Error processing uploaded audio: ' . $e->getMessage());
    }
  }
}
