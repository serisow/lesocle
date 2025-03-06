<?php
namespace Drupal\pipeline\Plugin\StepType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\file\FileInterface;
use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an image upload step type.
 *
 * @StepType(
 *   id = "upload_image_step",
 *   label = @Translation("Upload Image Step"),
 *   description = @Translation("Upload an image file to be used in the pipeline.")
 * )
 */
class UploadImageStep extends ConfigurableStepTypeBase implements StepTypeExecutableInterface
{

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return parent::create($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration()
  {
    return [
      'image_file_id' => NULL,
      'video_settings' => [
        'duration' => 5.0,
      ],
      'text_overlay' => [
        'enabled' => FALSE,
        'text' => '',
        'position' => 'bottom',
        'font_size' => 24,
        'font_color' => 'white',
        'background_color' => '',
        'custom_x' => 0,
        'custom_y' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::additionalConfigurationForm($form, $form_state);

    // Ensure form doesn't use caching because of the file field
    $form_state->disableCache();
    //$form_state->set('ajax', TRUE);
    // Image Upload field
    $form['image_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#description' => $this->t('Upload an image file (JPG, PNG, GIF, WebP). Maximum size: 2MB.'),
      '#default_value' => $this->configuration['image_file_id'] ? [$this->configuration['image_file_id']] : NULL,
      '#upload_location' => 'public://pipeline/uploads/images',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png gif jpg jpeg webp'],
        'FileSizeLimit' => ['fileLimit' => 2 * 1024 * 1024],
      ],
      '#required' => TRUE,
      '#process' => [
        [get_class($this), 'processManagedFile'],
      ],
    ];

    // Add video settings fieldset
    $form['video_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Video Settings'),
      '#open' => TRUE,
      '#description' => $this->t('Configure how this image will be used in video generation.'),
    ];

    $form['video_settings']['duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Duration (seconds)'),
      '#default_value' => $this->configuration['video_settings']['duration'] ?? 5.0,
      '#step' => 1,
      '#min' => 1,
      '#max' => 3600,
      '#description' => $this->t('How long this image should appear in generated videos (in seconds).'),
    ];

    // Add text overlay settings
    $form['text_overlay'] = [
      '#type' => 'details',
      '#title' => $this->t('Text Overlay'),
      '#open' => TRUE,
      '#description' => $this->t('Configure text to be overlaid on this image.'),
    ];

    $form['text_overlay']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable text overlay'),
      '#default_value' => $this->configuration['text_overlay']['enabled'] ?? FALSE,
    ];

    $form['text_overlay']['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text content'),
      '#default_value' => $this->configuration['text_overlay']['text'] ?? '',
      '#description' => $this->t('Text to overlay on the image. You can use {step_key} placeholders to insert content from previous steps.'),
      '#states' => [
        'visible' => [
          ':input[name="data[text_overlay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['text_overlay']['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => [
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
        'center' => $this->t('Center'),
        'top_left' => $this->t('Top Left'),
        'top_right' => $this->t('Top Right'),
        'bottom_left' => $this->t('Bottom Left'),
        'bottom_right' => $this->t('Bottom Right'),
        'custom' => $this->t('Custom coordinates'),
      ],
      '#default_value' => $this->configuration['text_overlay']['position'] ?? 'bottom',
      '#states' => [
        'visible' => [
          ':input[name="data[text_overlay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['text_overlay']['font_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Font size'),
      '#default_value' => $this->configuration['text_overlay']['font_size'] ?? 24,
      '#min' => 8,
      '#max' => 72,
      '#step' => 1,
      '#states' => [
        'visible' => [
          ':input[name="data[text_overlay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['text_overlay']['font_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font color'),
      '#default_value' => $this->configuration['text_overlay']['font_color'] ?? 'white',
      '#description' => $this->t('Color name (e.g., white, black) or hex value (e.g., #FFFFFF).'),
      '#states' => [
        'visible' => [
          ':input[name="data[text_overlay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['text_overlay']['background_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background color (optional)'),
      '#default_value' => $this->configuration['text_overlay']['background_color'] ?? '',
      '#description' => $this->t('Optional background box color. Leave empty for transparent background.'),
      '#states' => [
        'visible' => [
          ':input[name="data[text_overlay][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $coordinates_state = [
      'visible' => [
        ':input[name="data[text_overlay][enabled]"]' => ['checked' => TRUE],
        ':input[name="data[text_overlay][position]"]' => ['value' => 'custom'],
      ],
    ];

    $form['text_overlay']['custom_x'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom X position'),
      '#default_value' => $this->configuration['text_overlay']['custom_x'] ?? 0,
      '#description' => $this->t('X coordinate for custom positioning.'),
      '#states' => $coordinates_state,
    ];

    $form['text_overlay']['custom_y'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom Y position'),
      '#default_value' => $this->configuration['text_overlay']['custom_y'] ?? 0,
      '#description' => $this->t('Y coordinate for custom positioning.'),
      '#states' => $coordinates_state,
    ];

    return $form;
  }

  /**
   * Process callback for managed file to completely remove default AJAX behavior.
   */
  public static function processManagedFile($element, FormStateInterface $form_state, &$complete_form) {
    // Get the standard element with all its defaults
    $element = ManagedFile::processManagedFile($element, $form_state, $complete_form);
    // Remove all AJAX related attributes from upload button
    if (isset($element['upload_button'])) {
      unset($element['upload_button']['#ajax']);
      // Add a custom class to mark this as our custom upload
      $element['upload_button']['#attributes']['class'][] = 'custom-file-upload';
      // Prevent triggering Drupal's ajax
      $element['upload_button']['#attributes']['data-disable-ajax'] = 'true';
    }
    // Remove all AJAX from remove button
    if (isset($element['remove_button'])) {
      //unset($element['remove_button']['#ajax']);
      $element['remove_button']['#attributes']['class'][] = 'custom-file-remove';
      $element['remove_button']['#attributes']['data-disable-ajax'] = 'true';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // Handle file upload
    $image_file = $form_state->getValue(['data', 'image_file']);
    if (!empty($image_file) && !empty($image_file[0])) {
      $this->configuration['image_file_id'] = $image_file[0];
      // Make file permanent
      $file = $this->entityTypeManager->getStorage('file')->load($this->configuration['image_file_id']);
      if ($file instanceof FileInterface) {
        $file->setPermanent();
        $file->save();
      }
    }

    // Save video settings
    $this->configuration['video_settings'] = [
      'duration' => (float) $form_state->getValue(['data', 'video_settings', 'duration']),
    ];

    // Handle text overlay settings
    $this->configuration['text_overlay'] = [
      'enabled' => (bool) $form_state->getValue(['data', 'text_overlay', 'enabled']),
      'text' => $form_state->getValue(['data', 'text_overlay', 'text']),
      'position' => $form_state->getValue(['data', 'text_overlay', 'position']),
      'font_size' => (int) $form_state->getValue(['data', 'text_overlay', 'font_size']),
      'font_color' => $form_state->getValue(['data', 'text_overlay', 'font_color']),
      'background_color' => $form_state->getValue(['data', 'text_overlay', 'background_color']),
      'custom_x' => (int) $form_state->getValue(['data', 'text_overlay', 'custom_x']),
      'custom_y' => (int) $form_state->getValue(['data', 'text_overlay', 'custom_y']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string {
    try {
      // Validate file exists
      if (empty($this->configuration['image_file_id'])) {
        throw new \Exception('No image file has been uploaded.');
      }
      // Load the file
      $file = $this->entityTypeManager->getStorage('file')->load($this->configuration['image_file_id']);
      if (!$file instanceof FileInterface) {
        throw new \Exception('Invalid image file: File not found.');
      }

      // Create the result in the same format as the LLM image generation
      $result = [
        'file_id' => $file->id(),
        'uri' => $file->getFileUri(),
        'url' => $file->createFileUrl(FALSE),
        'filename' => $file->getFilename(),
        'mime_type' => $file->getMimeType(),
        'size' => $file->getSize(),
        'timestamp' => \Drupal::time()->getCurrentTime(),
        // Add video settings
        'video_settings' => [
          'duration' => $this->configuration['video_settings']['duration'],
        ],
      ];

      // Add text overlay settings if enabled
      if (!empty($this->configuration['text_overlay']['enabled'])) {
        $result['text_overlay'] = $this->configuration['text_overlay'];
      }

      // Add the result to the context with the appropriate output type
      $context['results'][$this->getStepOutputKey()] = [
        'output_type' => 'featured_image',
        'data' => json_encode($result),
      ];
      return json_encode($result);
    } catch (\Exception $e) {
      throw new \Exception('Error processing uploaded image: ' . $e->getMessage());
    }
  }
}
