<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\pipeline\Entity\PipelineInterface;
use Drupal\pipeline\Service\PipelineFileManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\pipeline\Plugin\StepTypeManager;

class PipelineStepTypeController extends ControllerBase implements ContainerInjectionInterface {
  /**
   * The step type manager service.
   *
   * @var \Drupal\pipeline\Plugin\StepTypeManager
   */
  protected $stepTypeManager;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The file manager service.
   *
   * @var \Drupal\pipeline\Service\PipelineFileManager
   */
  protected $fileManager;

  /**
   * Constructs a PipelineStepTypeController object.
   *
   * @param \Drupal\pipeline\Plugin\StepTypeManager $step_type_manager
   *   The step type manager service.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder service.
   * @param \Drupal\pipeline\Service\PipelineFileManager $file_manager
   *   The file manager service.
   */
  public function __construct(
    StepTypeManager $step_type_manager,
    FormBuilder $form_builder,
    PipelineFileManager $file_manager
  ) {
    $this->stepTypeManager = $step_type_manager;
    $this->formBuilder = $form_builder;
    $this->fileManager = $file_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.step_type'),
      $container->get('form_builder'),
      $container->get('pipeline.file_manager')
    );
  }

  public function stepTypeAjax(Request $request, PipelineInterface $pipeline) {
    $method = $request->request->get('_method', 'POST');
    $step_type = $request->request->get('step_type');
    $uuid = $request->request->get('uuid');

    $form_id = $request->request->get('form_id');
    if ($method === 'PUT' && $uuid) {
      return $this->updateStepTypeAjax($request, $pipeline, $step_type, $uuid);
    } else {
      return $this->addStepTypeAjax($request, $pipeline, $step_type, $form_id);
    }
  }

  public function addStepTypeAjax(Request $request, PipelineInterface $pipeline, $step_type, $form_id) {
    $response = new AjaxResponse();
    $requestData = $request->request->all();

    if ($this->fileManager->isFileBasedStepType($step_type)) {
      // Process the step type with the uploaded file IDs
      $this->fileManager->processUploadedFiles($requestData, 'add');
    }

    // Build the form state
    $form_state = (new FormState())
      ->setValues($requestData)
      ->set('pipeline', $pipeline)
      ->set('step_type', $step_type);

    // Disable form cache for forms with file uploads
    if ($this->fileManager->isFileBasedStepType($step_type)) {
      // Remove uploaded files from form state to prevent serialization
      $this->fileManager->removeUploadedFilesFromFormState($form_state);
      $form_state->disableCache();
    }
    $form_state->disableRedirect();
    $form = $this->formBuilder->buildForm('\Drupal\pipeline\Form\PipelineStepTypeAddForm', $form_state);

    // Validate and submit the form
    if ($form_state->isValidationComplete() || $form_state->isExecuted()) {
      $this->formBuilder->processForm('\Drupal\pipeline\Form\PipelineStepTypeAddForm', $form, $form_state);
    }
    if ($form_state->hasAnyErrors()) {
      $errors = [];
      foreach ($form_state->getErrors() as $name => $error) {
        $errors[$name] = $error->render();
      }
      $response->addCommand(new ReplaceCommand('.ui-dialog-content', $form));
      $response->addCommand(new InvokeCommand(NULL, 'showFormErrors', [$errors]));
    } else {
      // If successful, add commands to close the modal and refresh the page
      // No errors, so create and save the step type
      $step_type_instance = $this->stepTypeManager->createInstance($step_type);

      if ($this->fileManager->isFileBasedStepType($step_type)) {
        $cleaned_values = $requestData;
      } else{
        $cleaned_values = $form_state->getValues();
      }
      // Filter out empty or not enabled  text_blocks for UploadImageStep
      $cleaned_values = $this->cleanTextBlocks($cleaned_values, $step_type);

      $step_type_instance->setConfiguration($cleaned_values);
      // Set the weight to be the last in the list
      $current_step_count = count($pipeline->getStepTypes());
      $step_type_instance->setWeight($current_step_count);
      // Add the step type to the pipeline
      $uuid = $pipeline->addStepType($step_type_instance->getConfiguration());
      //@todo inject the service messenger
      \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
      \Drupal::messenger()->addStatus($this->t('Step type added successfully.'));
      // Save the pipeline entity
      $pipeline->save();
      $url = Url::fromRoute('entity.pipeline.edit_steps', ['pipeline' => $pipeline->id()]);
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand($url->toString()));
    }
    return $response;
  }

  /*
   * Handle the click on the submit button: "Update step type" on the modal
   */
  protected function updateStepTypeAjax(Request $request, PipelineInterface $pipeline, $step_type, $uuid) {
    $response = new AjaxResponse();
    try {
      $step_type = $pipeline->getStepType($uuid);

      if (!$step_type) {
        throw new \Exception('Step type not found');
      }
      $requestData = $request->request->all();

      if ($this->fileManager->isFileBasedStepType($step_type->getPluginId())) {
        // Process the step type with the uploaded file IDs
        $this->fileManager->processUploadedFiles($requestData, 'update');
      }

      $form_state = (new FormState())
        ->setValues($requestData)
        ->set('pipeline', $pipeline)
        ->set('step_type', $step_type)
        ->set('uuid', $uuid);

      // Remove uploaded files from form state to prevent serialization
      $this->fileManager->removeUploadedFilesFromFormState($form_state);

      $form_state->disableRedirect();
      $form = $this->formBuilder->buildForm('Drupal\pipeline\Form\PipelineStepTypeEditForm', $form_state);

      if ($form_state->isValidationComplete() || $form_state->isExecuted()) {
        $this->formBuilder->processForm('Drupal\pipeline\Form\PipelineStepTypeEditForm', $form, $form_state);
      }

      if ($form_state->hasAnyErrors()) {
        $errors = [];
        foreach ($form_state->getErrors() as $name => $error) {
          $errors[$name] = $error->render();
        }
        $form = $this->formBuilder->rebuildForm('Drupal\pipeline\Form\PipelineStepTypeEditForm', $form_state, $form);
        $response->addCommand(new ReplaceCommand('.ui-dialog-content', $form));
        $response->addCommand(new InvokeCommand(NULL, 'showFormErrors', [$errors]));
      } else {
        if ($this->fileManager->isFileBasedStepType($step_type->getPluginId())) {
          $cleaned_values = $requestData;
        } else{
          $cleaned_values = $form_state->getValues();
        }
        // Filter out empty or not enabled  text_blocks for UploadImageStep
        $cleaned_values = $this->cleanTextBlocks($cleaned_values, $step_type->getPluginId());
        $step_type->setConfiguration($cleaned_values);
        $pipeline->save();
        //@todo inject the service messenger
        \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
        \Drupal::messenger()->addStatus($this->t('Step type updated successfully.'));

        $url = Url::fromRoute('entity.pipeline.edit_steps', ['pipeline' => $pipeline->id()]);
        $response->addCommand(new CloseModalDialogCommand());
        $response->addCommand(new RedirectCommand($url->toString()));
        $response->addCommand(new InvokeCommand(NULL, 'showMessage', ['Step type updated successfully.', 'status']));
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('pipeline')->error('Error updating step type: @error', ['@error' => $e->getMessage()]);
      $response->addCommand(new InvokeCommand(NULL, 'showMessage', ['An error occurred while updating the step type.', 'error']));
    }

    return $response;
  }

  // Handler to update the prompt field used on LLM Step.
  public function updatePrompt(Request $request, PipelineInterface $pipeline, $step_type) {
    $prompt_template_id = $request->request->get('prompt_template_id');
    if ($prompt_template_id) {
      $prompt_template = $this->entityTypeManager()->getStorage('prompt_template')->load($prompt_template_id);
      if ($prompt_template) {
        $prompt = $prompt_template->getTemplate();
        return new JsonResponse(['prompt' => $prompt]);
      }
    }
    return new JsonResponse(['error' => 'Prompt template not found'], 404);
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
   * Filters out disabled or empty text blocks from configuration data.
   * Also removes unnecessary custom coordinates for predefined positions.
   *
   * Only applies to UploadImageStep type plugins.
   *
   * @param array $values
   *   The configuration values to clean.
   * @param string $plugin_id
   *   The plugin ID of the step type.
   *
   * @return array
   *   The cleaned configuration values.
   */
  protected function cleanTextBlocks(array $values, string $plugin_id): array {
    // Only apply cleaning to UploadImageStep
    if ($plugin_id === 'upload_image_step' && isset($values['data']['text_blocks']) && is_array($values['data']['text_blocks'])) {
      $filtered_blocks = [];
      foreach ($values['data']['text_blocks'] as $block) {
        // Only keep blocks that are enabled AND have non-empty text
        if (!empty($block['enabled']) && !empty(trim($block['text'] ?? ''))) {
          // Format the text content using our formatting method
          $formatted_text = $this->formatText($block['text'], $block['id']);
          // Create a clean block with only required properties
          $clean_block = [
            'id' => $block['id'],
            'enabled' => true,
            'text' => $formatted_text,
            'position' => $block['position'] ?? 'bottom',
            'font_size' => (int) ($block['font_size'] ?? 24),
            'font_color' => $block['font_color'] ?? 'white',
            'font_family' => $block['font_family'] ?? 'sans',
            'font_style' => $block['font_style'] ?? 'normal',
            'background_color' => $block['background_color'] ?? '',
          ];

          // Only include custom coordinates if position is set to "custom"
          if (($block['position'] ?? '') === 'custom') {
            $clean_block['custom_x'] = isset($block['custom_x']) ? (int) $block['custom_x'] : 0;
            $clean_block['custom_y'] = isset($block['custom_y']) ? (int) $block['custom_y'] : 0;
          }

          // Add animation properties if they exist
          if (isset($block['animation'])) {
            $clean_block['animation'] = [
              'type' => $block['animation']['type'] ?? 'none',
              'duration' => (float) ($block['animation']['duration'] ?? 1.0),
              'delay' => (float) ($block['animation']['delay'] ?? 0.0),
              'easing' => $block['animation']['easing'] ?? 'linear',
            ];
          }

          $filtered_blocks[] = $clean_block;
        }
      }
      $values['data']['text_blocks'] = $filtered_blocks;
    }
    return $values;
  }
}
