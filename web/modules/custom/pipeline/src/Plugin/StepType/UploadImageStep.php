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
  protected function additionalDefaultConfiguration() {
    return [
      'image_file_id' => NULL,
      'video_settings' => [
        'duration' => 5.0,
      ],

      // Predefined text blocks with default settings
      'text_blocks' => [
        [
          'id' => 'title_block',
          'enabled' => FALSE,
          'text' => '',
          'position' => 'top',
          'font_size' => 36,
          'font_color' => 'white',
          'background_color' => 'rgba(0,0,0,0.5)',
          'custom_x' => 0,
          'custom_y' => 0,
          'animation' => [
            'type' => 'none',
            'duration' => 1.0,
            'delay' => 0.0,
            'easing' => 'linear',
          ]
        ],
        [
          'id' => 'subtitle_block',
          'enabled' => FALSE,
          'text' => '',
          'position' => 'center',
          'font_size' => 28,
          'font_color' => 'white',
          'background_color' => '',
          'custom_x' => 0,
          'custom_y' => 0,
          'animation' => [
            'type' => 'none',
            'duration' => 1.0,
            'delay' => 0.0,
            'easing' => 'linear',
          ]
        ],
        [
          'id' => 'body_block',
          'enabled' => FALSE,
          'text' => '',
          'position' => 'center',
          'font_size' => 24,
          'font_color' => 'white',
          'background_color' => '',
          'custom_x' => 0,
          'custom_y' => 60,
          'animation' => [
            'type' => 'none',
            'duration' => 1.0,
            'delay' => 0.0,
            'easing' => 'linear',
          ]
        ],
        [
          'id' => 'caption_block',
          'enabled' => FALSE,
          'text' => '',
          'position' => 'bottom',
          'font_size' => 20,
          'font_color' => 'white',
          'background_color' => '',
          'custom_x' => 0,
          'custom_y' => 0,
          'animation' => [
            'type' => 'none',
            'duration' => 1.0,
            'delay' => 0.0,
            'easing' => 'linear',
          ]
        ],
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

    // Text blocks section
    $form['text_blocks'] = [
      '#type' => 'details',
      '#title' => $this->t('Text Elements'),
      '#open' => TRUE,
      '#description' => $this->t('Configure text elements to overlay on the image.'),
      '#tree' => TRUE,
    ];

    // Get text blocks from configuration or use defaults
    $text_blocks = $this->configuration['text_blocks'] ?? $this->additionalDefaultConfiguration()['text_blocks'];

    // Create form elements for each predefined block
    foreach ($text_blocks as $index => $block) {
      $block_id = $block['id'];
      $title = $this->getBlockTitle($block_id);

      $form['text_blocks'][$index] = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => !empty($block['enabled']),
        '#attributes' => [
          'class' => ['text-block-form'],
          'data-block-id' => $block_id,
        ],
      ];

      $form['text_blocks'][$index]['id'] = [
        '#type' => 'hidden',
        '#value' => $block_id,
      ];

      $form['text_blocks'][$index]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable this text element'),
        '#default_value' => $block['enabled'],
      ];

      // Only show these fields if enabled
      $states_visible = [
        'visible' => [
          ':input[name="data[text_blocks][' . $index . '][enabled]"]' => ['checked' => TRUE],
        ],
      ];

      $form['text_blocks'][$index]['text'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Text content'),
        '#default_value' => $block['text'],
        '#description' => $this->t('Text to overlay on the image. You can use {step_key} placeholders to insert content from previous steps.'),
        '#rows' => 3,
        '#states' => $states_visible,
      ];

      $form['text_blocks'][$index]['font_size'] = [
        '#type' => 'number',
        '#title' => $this->t('Font size'),
        '#default_value' => $block['font_size'],
        '#min' => 8,
        '#max' => 72,
        '#step' => 1,
        '#states' => $states_visible,
      ];

      $form['text_blocks'][$index]['font_color'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Font color'),
        '#default_value' => $block['font_color'],
        '#description' => $this->t('Color name (e.g., white, black) or hex value (e.g., #FFFFFF).'),
        '#states' => $states_visible,
      ];

      $form['text_blocks'][$index]['background_color'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Background color (optional)'),
        '#default_value' => $block['background_color'],
        '#description' => $this->t('Optional background box color. Leave empty for transparent background.'),
        '#states' => $states_visible,
      ];

      $form['text_blocks'][$index]['position'] = [
        '#type' => 'select',
        '#title' => $this->t('Position'),
        '#options' => [
          'top_left' => $this->t('Top Left'),
          'top' => $this->t('Top Center'),
          'top_right' => $this->t('Top Right'),
          'left' => $this->t('Middle Left'),
          'center' => $this->t('Center'),
          'right' => $this->t('Middle Right'),
          'bottom_left' => $this->t('Bottom Left'),
          'bottom' => $this->t('Bottom Center'),
          'bottom_right' => $this->t('Bottom Right'),
          'custom' => $this->t('Custom coordinates'),
        ],
        '#default_value' => $block['position'],
        '#states' => $states_visible,
      ];

      // Custom coordinates, visible only when position is set to 'custom'
      $custom_coords_visible = [
        'visible' => [
          ':input[name="data[text_blocks][' . $index . '][enabled]"]' => ['checked' => TRUE],
          ':input[name="data[text_blocks][' . $index . '][position]"]' => ['value' => 'custom'],
        ],
      ];

      $form['text_blocks'][$index]['custom_x'] = [
        '#type' => 'number',
        '#title' => $this->t('Custom X position'),
        '#default_value' => $block['custom_x'],
        '#description' => $this->t('X coordinate for custom positioning.'),
        '#states' => $custom_coords_visible,
      ];

      $form['text_blocks'][$index]['custom_y'] = [
        '#type' => 'number',
        '#title' => $this->t('Custom Y position'),
        '#default_value' => $block['custom_y'],
        '#description' => $this->t('Y coordinate for custom positioning.'),
        '#states' => $custom_coords_visible,
      ];

      // Animation settings
      $form['text_blocks'][$index]['animation'] = [
        '#type' => 'details',
        '#title' => $this->t('Animation'),
        '#open' => FALSE,
        '#states' => $states_visible,
      ];

      $form['text_blocks'][$index]['animation']['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Animation Type'),
        '#options' => [
          'none' => $this->t('None'),
          'fade' => $this->t('Fade'),
          'slide' => $this->t('Slide'),
          'scale' => $this->t('Scale'),
          'typewriter' => $this->t('Typewriter'),
        ],
        '#default_value' => $block['animation']['type'] ?? 'none',
      ];

      $form['text_blocks'][$index]['animation']['duration'] = [
        '#type' => 'number',
        '#title' => $this->t('Duration (seconds)'),
        '#default_value' => $block['animation']['duration'] ?? 1.0,
        '#min' => 0.1,
        '#max' => 5.0,
        '#step' => 0.1,
        '#states' => [
          'visible' => [
            ':input[name="data[text_blocks][' . $index . '][animation][type]"]' => ['!value' => 'none'],
          ],
        ],
      ];

      $form['text_blocks'][$index]['animation']['delay'] = [
        '#type' => 'number',
        '#title' => $this->t('Delay (seconds)'),
        '#default_value' => $block['animation']['delay'] ?? 0.0,
        '#min' => 0.0,
        '#max' => 10.0,
        '#step' => 0.1,
        '#states' => [
          'visible' => [
            ':input[name="data[text_blocks][' . $index . '][animation][type]"]' => ['!value' => 'none'],
          ],
        ],
      ];

      $form['text_blocks'][$index]['animation']['easing'] = [
        '#type' => 'select',
        '#title' => $this->t('Easing'),
        '#options' => [
          'linear' => $this->t('Linear'),
          'ease-in' => $this->t('Ease In'),
          'ease-out' => $this->t('Ease Out'),
          'ease-in-out' => $this->t('Ease In-Out'),
        ],
        '#default_value' => $block['animation']['easing'] ?? 'linear',
        '#states' => [
          'visible' => [
            ':input[name="data[text_blocks][' . $index . '][animation][type]"]' => ['!value' => 'none'],
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * Get a human-readable title for a block based on its ID.
   */
  protected function getBlockTitle($block_id) {
    $titles = [
      'title_block' => $this->t('Title Text'),
      'subtitle_block' => $this->t('Subtitle Text'),
      'body_block' => $this->t('Body Text'),
      'caption_block' => $this->t('Caption Text'),
    ];

    return $titles[$block_id] ?? $this->t('Text Element');
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

    // Process text blocks
    $text_blocks = [];
    $blocks_values = $form_state->getValue(['data', 'text_blocks']);

    if (is_array($blocks_values)) {
      foreach ($blocks_values as $index => $values) {
        // Only add blocks that are both enabled AND have non-empty text content
        if (!empty($values['enabled']) && !empty(trim($values['text'] ?? ''))) {
          $text_blocks[] = [
            'id' => $values['id'],
            'enabled' => true,
            'text' => $values['text'],
            'position' => $values['position'] ?? 'bottom',
            'font_size' => (int) ($values['font_size'] ?? 24),
            'font_color' => $values['font_color'] ?? 'white',
            'background_color' => $values['background_color'] ?? '',
            'custom_x' => isset($values['custom_x']) ? (int) $values['custom_x'] : 0,
            'custom_y' => isset($values['custom_y']) ? (int) $values['custom_y'] : 0,
            'animation' => [
              'type' => $values['animation']['type'] ?? 'none',
              'duration' => (float) ($values['animation']['duration'] ?? 1.0),
              'delay' => (float) ($values['animation']['delay'] ?? 0.0),
              'easing' => $values['animation']['easing'] ?? 'linear',
            ],
          ];
        }
      }
    }

    $this->configuration['text_blocks'] = $text_blocks;
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

      // Add enabled text blocks to the result
      $enabled_blocks = [];
      foreach ($this->configuration['text_blocks'] as $block) {
        if (!empty($block['enabled'])) {
          $enabled_blocks[] = $block;
        }
      }

      if (!empty($enabled_blocks)) {
        $result['text_blocks'] = $enabled_blocks;
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
