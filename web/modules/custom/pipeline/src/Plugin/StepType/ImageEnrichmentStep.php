<?php

namespace Drupal\pipeline\Plugin\StepType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an image enrichment step type.
 *
 * @StepType(
 *   id = "image_enrichment_step",
 *   label = @Translation("Image Enrichment Step"),
 *   description = @Translation("Adds text overlays and animations to images from previous steps.")
 * )
 */
class ImageEnrichmentStep extends ConfigurableStepTypeBase implements StepTypeExecutableInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return parent::create($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return [
      'duration' => 5.0,
      'text_blocks' => [
        [
          'id' => 'title_block',
          'enabled' => false,
          'text' => '',
          'position' => 'top',
          'font_size' => 36,
          'font_color' => 'white',
          'font_family' => 'sans',
          'font_style' => 'normal',
          'background_color' => 'rgba(0,0,0,0.5)',
          'custom_x' => 0,
          'custom_y' => 0,
          'animation' => [
            'type' => 'fade',
            'duration' => 1.0,
            'delay' => 0.0,
            'easing' => 'linear',
          ],
        ],
        [
          'id' => 'subtitle_block',
          'enabled' => false,
          'text' => '',
          'content_source' => '',
          'position' => 'center',
          'font_size' => 28,
          'font_color' => 'white',
          'font_family' => 'sans',
          'font_style' => 'normal',
          'background_color' => '',
          'custom_x' => 0,
          'custom_y' => 0,
          'animation' => [
            'type' => 'fade',
            'duration' => 1.0,
            'delay' => 0.5,
            'easing' => 'linear',
          ],
        ],
        [
          'id' => 'body_block',
          'enabled' => false,
          'text' => '',
          'content_source' => '',
          'position' => 'center',
          'font_size' => 24,
          'font_color' => 'white',
          'font_family' => 'sans',
          'font_style' => 'normal',
          'background_color' => '',
          'custom_x' => 0,
          'custom_y' => 60,
          'animation' => [
            'type' => 'fade',
            'duration' => 1.0,
            'delay' => 1.0,
            'easing' => 'linear',
          ],
        ],
        [
          'id' => 'caption_block',
          'enabled' => false,
          'text' => '',
          'content_source' => '',
          'position' => 'bottom',
          'font_size' => 20,
          'font_color' => 'white',
          'font_family' => 'sans',
          'font_style' => 'normal',
          'background_color' => '',
          'custom_x' => 0,
          'custom_y' => 0,
          'animation' => [
            'type' => 'fade',
            'duration' => 1.0,
            'delay' => 1.5,
            'easing' => 'linear',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::additionalConfigurationForm($form, $form_state);

    // We use Required Steps functionality instead of a specific source_image_step field
    // The step will look for image data in the required steps with 'featured_image' output type

    // Duration setting
    $form['duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Duration (seconds)'),
      '#description' => $this->t('How long this image should appear in the video.'),
      '#default_value' => $this->configuration['duration'],
      '#min' => 1,
      '#max' => 60,
      '#step' => 0.5,
      '#required' => TRUE,
    ];

    // Text blocks container
    $form['text_blocks'] = [
      '#type' => 'details',
      '#title' => $this->t('Text Elements'),
      '#open' => TRUE,
      '#description' => $this->t('Configure text elements to overlay on the image.'),
      '#tree' => TRUE,
    ];

    $text_blocks = $this->configuration['text_blocks'];

    // Create form elements for each text block
    foreach ($text_blocks as $index => $block) {
      $this->buildTextBlockForm($form, $form_state, $index, $block);
    }

    return $form;
  }

  /**
   * Gets the current pipeline entity.
   *
   * @return \Drupal\pipeline\Entity\PipelineInterface|null
   *   The pipeline entity or NULL if not found.
   */
  protected function getPipeline() {
    // Try to get from request attribute first (for UI operations)
    if ($this->requestStack->getCurrentRequest()->attributes->has('pipeline')) {
      return $this->requestStack->getCurrentRequest()->attributes->get('pipeline');
    }

    // During execution, get from configuration
    $configuration = $this->getConfiguration();
    if (isset($configuration['pipeline_id'])) {
      return $this->entityTypeManager->getStorage('pipeline')->load($configuration['pipeline_id']);
    }

    // For scheduled executions, try to get from context
    $pipeline_id = \Drupal::state()->get('pipeline.current_pipeline_id');
    if ($pipeline_id) {
      return $this->entityTypeManager->getStorage('pipeline')->load($pipeline_id);
    }

    return NULL;
  }

  /**
   * Builds form elements for a text block.
   */
  protected function buildTextBlockForm(array &$form, FormStateInterface $form_state, $index, array $block) {
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
      '#default_value' => $block['text'] ?? '',
      '#description' => $this->t('Text to overlay on the image. Only used if no content source is selected.'),
      '#rows' => 3,
      '#states' => $states_visible,
    ];

    // Position control
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
    $form['text_blocks'][$index]['font_family'] = [
      '#type' => 'select',
      '#title' => $this->t('Font'),
      '#options' => $this->getFontOptions(),
      '#default_value' => $block['font_family'] ?? 'default',
      '#description' => $this->t('Select a font for this text block.'),
      '#states' => $states_visible,
    ];

    $form['text_blocks'][$index]['font_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Font Style'),
      '#options' => [
        'normal' => $this->t('Normal'),
        'outline' => $this->t('Outline'),
        'shadow' => $this->t('Shadow'),
        'outline_shadow' => $this->t('Outline + Shadow'),
      ],
      '#default_value' => $block['font_style'] ?? 'normal',
      '#description' => $this->t('Apply additional styling to improve visibility.'),
      '#states' => $states_visible,
    ];

    $form['text_blocks'][$index]['background_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background color (optional)'),
      '#default_value' => $block['background_color'],
      '#description' => $this->t('Optional background box color. Leave empty for transparent background.'),
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
      ],
      '#default_value' => $block['animation']['type'] ?? 'none',
    ];

    $animation_settings_visible = [
      'visible' => [
        ':input[name="data[text_blocks][' . $index . '][enabled]"]' => ['checked' => TRUE],
        ':input[name="data[text_blocks][' . $index . '][animation][type]"]' => ['!value' => 'none'],
      ],
    ];

    $form['text_blocks'][$index]['animation']['duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Duration (seconds)'),
      '#default_value' => $block['animation']['duration'] ?? 1.0,
      '#min' => 0.1,
      '#max' => 5.0,
      '#step' => 0.1,
      '#states' => $animation_settings_visible,
    ];

    $form['text_blocks'][$index]['animation']['delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Delay (seconds)'),
      '#default_value' => $block['animation']['delay'] ?? 0.0,
      '#min' => 0.0,
      '#max' => 10.0,
      '#step' => 0.1,
      '#states' => $animation_settings_visible,
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
      '#states' => $animation_settings_visible,
    ];
  }

  /**
   * Gets a human-readable title for a block based on its ID.
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
   * Gets available content sources from other steps.
   */
  protected function getContentSources() {
    $options = [];
    $pipeline = $this->getPipeline();

    if (!$pipeline) {
      return $options;
    }

    foreach ($pipeline->getStepTypes() as $step_type) {
      $step_output_key = $step_type->getStepOutputKey();

      if (!empty($step_output_key)) {
        $options[$step_output_key] = $this->t('@desc (@key)', [
          '@desc' => $step_type->getStepDescription(),
          '@key' => $step_output_key,
        ]);
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['duration'] = (float) $form_state->getValue(['data', 'duration']);

    // Process text blocks
    $text_blocks = [];
    $blocks_values = $form_state->getValue(['data', 'text_blocks']) ?? [];

    if (is_array($blocks_values)) {
      foreach ($blocks_values as $index => $values) {
        // Only add blocks that are enabled
        if (!empty($values['enabled'])) {
          $text_blocks[] = [
            'id' => $values['id'],
            'enabled' => true,
            'text' => $values['text'] ?? '',
            'position' => $values['position'] ?? 'bottom',
            'font_size' => (int) ($values['font_size'] ?? 24),
            'font_color' => $values['font_color'] ?? 'white',
            'font_family' => $values['font_family'] ?? 'sans',
            'font_style' => $values['font_style'] ?? 'normal',
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
      // Find the news items data in the context
      $newsItems = null;

      // First identify which step has our source data by looking at all results
      foreach ($context['results'] as $step_key => $stepResult) {
        // Debug logging to see what output_types we're dealing with
        $this->logger->debug('Step @key has output_type: @type', [
          '@key' => $step_key,
          '@type' => $stepResult['output_type'] ?? 'none',
        ]);

        // Try to parse data regardless of the output_type
        if (isset($stepResult['data'])) {
          $data = $stepResult['data'];

          // If it's a string, try to parse as JSON
          if (is_string($data)) {
            $decoded = json_decode($data, TRUE);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
              // Check if this looks like our news items array (has elements with article_id, image_info)
              if (!empty($decoded[0]) && isset($decoded[0]['article_id']) && isset($decoded[0]['image_info'])) {
                $newsItems = $decoded;
                $this->logger->debug('Found news items in step @key', ['@key' => $step_key]);
                break;
              }
            }
          }
          // If it's already an array, check its structure
          elseif (is_array($data) && !empty($data[0]) && isset($data[0]['article_id']) && isset($data[0]['image_info'])) {
            $newsItems = $data;
            $this->logger->debug('Found news items in step @key', ['@key' => $step_key]);
            break;
          }
        }
      }

      if (!$newsItems) {
        throw new \Exception('No news items found in the context. Make sure a news generation step comes before this step.');
      }

      // Get target news item based on position
      $targetIndex = $this->getTargetIndex();
      $this->logger->debug('Using target index @index for enrichment', ['@index' => $targetIndex]);

      // Handle case where there are fewer news items than enrichment steps
      if ($targetIndex >= count($newsItems)) {
        $this->logger->warning('Not enough news items for this enrichment step. Using last available item.', [
          'target_index' => $targetIndex,
          'available_items' => count($newsItems),
        ]);
        $targetIndex = count($newsItems) - 1;
      }

      // Get the target news item
      $newsItem = $newsItems[$targetIndex];

      // Validate image data
      if (!isset($newsItem['image_info']) || !isset($newsItem['image_info']['file_id'])) {
        throw new \Exception('News item does not contain valid image information.');
      }

      // Load the file to validate
      $file = $this->entityTypeManager->getStorage('file')->load($newsItem['image_info']['file_id']);
      if (!$file) {
        throw new \Exception('Image file not found: ID ' . $newsItem['image_info']['file_id']);
      }

      // Process text blocks with auto-populated content
      $processedTextBlocks = [];
      foreach ($this->configuration['text_blocks'] as $block) {
        if (!empty($block['enabled'])) {
          $processedBlock = $block;
           $sourceText = '';
            switch ($block['id']) {
              case 'title_block':
                $sourceText = $newsItem['headline'] ?? '';
                break;
              case 'subtitle_block':
                $sourceText = $newsItem['caption'] ?? '';
                break;
              case 'body_block':
                $sourceText = $newsItem['summary'] ?? '';
                break;
              case 'caption_block':
                $sourceText = $newsItem['caption'] ?? '';
                break;
            }
            // Format the text for video display
            $processedBlock['text'] = $this->formatText($sourceText, $block['id']);
          $processedTextBlocks[] = $processedBlock;
        }
      }

      // Create the result following the UploadImageStep format
      $result = [
        'file_id' => $newsItem['image_info']['file_id'],
        'uri' => $newsItem['image_info']['uri'] ?? $file->getFileUri(),
        'url' => $newsItem['image_info']['url'] ?? $file->createFileUrl(FALSE),
        'filename' => $newsItem['image_info']['filename'] ?? $file->getFilename(),
        'mime_type' => $newsItem['image_info']['mime'] ?? $file->getMimeType(),
        'size' => $newsItem['image_info']['size'] ?? $file->getSize(),
        'timestamp' => \Drupal::time()->getCurrentTime(),
        // Video settings
        'video_settings' => [
          'duration' => $this->configuration['duration'],
        ],
        // Add processed text blocks
        'text_blocks' => $processedTextBlocks,
      ];

      // Add the result to the context with the consistent output type
      $context['results'][$this->getStepOutputKey()] = [
        'output_type' => 'featured_image',
        'data' => json_encode($result),
      ];

      return json_encode($result);
    }
    catch (\Exception $e) {
      throw new \Exception('Error processing image enrichment: ' . $e->getMessage());
    }
  }

  /**
   * Gets the index of the news item to process based on this step's position.
   */
  /**
   * Gets the index of the news item to process based on this step's UUID.
   */
  protected function getTargetIndex() {
    // Use the last character of the UUID as a simple deterministic index
    $uuid = $this->getUuid();
    $lastChar = substr($uuid, -1);

    // Convert the last character to a numeric index
    $index = hexdec($lastChar) % 10; // Will give a value between 0-9

    // Add logging
    $this->logger->debug('Generated target index @index from UUID @uuid', [
      '@index' => $index,
      '@uuid' => $uuid,
    ]);

    return $index;
  }

  /**
   * Formats text for display in video overlays.
   *
   * @param string $text
   *   The original text.
   * @param string $blockType
   *   The type of block (title_block, body_block, etc.).
   *
   * @return string
   *   The formatted text.
   */
  protected function formatText($text, $blockType) {
    // Define character limits and line lengths based on block type
    // Increase limits but stay conservative to ensure FFmpeg compatibility
    $config = [
      'title_block' => ['maxChars' => 80, 'lineLength' => 40, 'maxLines' => 2],
      'subtitle_block' => ['maxChars' => 100, 'lineLength' => 45, 'maxLines' => 2],
      'body_block' => ['maxChars' => 450, 'lineLength' => 100, 'maxLines' => 8],
      'caption_block' => ['maxChars' => 120, 'lineLength' => 45, 'maxLines' => 3],
    ];

    // Default values if block type is not recognized
    $maxChars = 100;
    $lineLength = 40;
    $maxLines = 3;

    // Get the specific config for this block type
    if (isset($config[$blockType])) {
      $maxChars = $config[$blockType]['maxChars'];
      $lineLength = $config[$blockType]['lineLength'];
      $maxLines = $config[$blockType]['maxLines'];
    }

    // Preprocess text: remove extra spaces and normalize line breaks
    $text = trim(preg_replace('/\s+/', ' ', $text));

    // Trim text if it exceeds the maximum length
    $original_length = mb_strlen($text);
    if ($original_length > $maxChars) {
      // Try sentence-based truncation first for more natural reading
      $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
      $truncated = '';
      foreach ($sentences as $sentence) {
        if (mb_strlen($truncated . ' ' . $sentence) <= $maxChars) {
          $truncated .= ($truncated ? ' ' : '') . $sentence;
        } else {
          // If adding this sentence would exceed limits, stop
          break;
        }
      }

      // If we got some sentences, use them
      if (mb_strlen($truncated) > 0) {
        $text = $truncated;
      } else {
        // Otherwise fall back to word boundary truncation
        $text = mb_substr($text, 0, $maxChars);
        $lastSpace = mb_strrpos($text, ' ');
        if ($lastSpace !== false) {
          $text = mb_substr($text, 0, $lastSpace);
        }
        $text .= '...';
      }
    }

    // Add smart line breaks for readability
    $words = explode(' ', $text);
    $lines = [];
    $currentLine = '';
    $lineCount = 0;

    foreach ($words as $word) {
      // Check if adding the next word would exceed the line length
      if (mb_strlen($currentLine . ' ' . $word) > $lineLength && !empty($currentLine)) {
        // Add the current line to the lines array and start a new line
        $lines[] = $currentLine;
        $currentLine = $word;
        $lineCount++;

        // If we've reached max lines, start combining remaining text
        if ($lineCount >= $maxLines - 1) {
          // Add all remaining words with space
          $remainingWords = array_slice($words, array_search($word, $words) + 1);
          if (!empty($remainingWords)) {
            $currentLine .= ' ' . implode(' ', $remainingWords);

            // If the last line is too long, truncate it with ellipsis
            if (mb_strlen($currentLine) > $lineLength) {
              $currentLine = mb_substr($currentLine, 0, $lineLength - 3) . '...';
            }
          }
          break;
        }
      } else {
        // Add the word to the current line with a space if not the first word
        $currentLine = empty($currentLine) ? $word : $currentLine . ' ' . $word;
      }
    }

    // Add the last line if not empty and we haven't reached max lines
    if (!empty($currentLine) && $lineCount < $maxLines) {
      $lines[] = $currentLine;
    }

    // Join the lines with line breaks - use \n for FFmpeg
    return implode("\n", $lines);
  }


  /**
   * Gets font options for form select elements.
   *
   * @return array
   *   Array of font options.
   */
  protected function getFontOptions() {
    // Get the font service from the container
    $fontService = \Drupal::service('pipeline.font_service');
    return $fontService->getFontOptions();
  }
}
