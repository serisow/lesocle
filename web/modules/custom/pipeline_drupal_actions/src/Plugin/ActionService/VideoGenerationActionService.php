<?php
namespace Drupal\pipeline_drupal_actions\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\file\FileRepositoryInterface;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "video_generation",
 *   label = @Translation("Video Generation Action")
 * )
 */
class VideoGenerationActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface
{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a VideoGenerationActionService object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(
    array                         $configuration,
                                  $plugin_id,
                                  $plugin_definition,
    EntityTypeManagerInterface    $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FileRepositoryInterface       $file_repository,
    FileSystemInterface           $file_system
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->fileRepository = $file_repository;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('file.repository'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration)
  {
    // Video quality options.
    $form['video_quality'] = [
      '#type' => 'select',
      '#title' => $this->t('Video Quality'),
      '#options' => [
        'low' => $this->t('Low (480p)'),
        'medium' => $this->t('Medium (720p)'),
        'high' => $this->t('High (1080p)'),
      ],
      '#default_value' => $configuration['video_quality'] ?? 'medium',
      '#description' => $this->t('Select the quality of the generated video.'),
    ];

    // Output format options.
    $form['output_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Output Format'),
      '#options' => [
        'mp4' => $this->t('MP4 (H.264)'),
        'webm' => $this->t('WebM (VP9)'),
      ],
      '#default_value' => $configuration['output_format'] ?? 'mp4',
      '#description' => $this->t('Select the output format for the video.'),
    ];

    // Add transition settings
    $form['transition_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Transition Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['transition_settings']['transition_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Transition Type'),
      '#options' => [
        'fade' => $this->t('Fade'),
        'fadeblack' => $this->t('Fade through black'),
        'fadewhite' => $this->t('Fade through white'),
        'distance' => $this->t('Distance'),
        'wipeleft' => $this->t('Wipe left'),
        'wiperight' => $this->t('Wipe right'),
        'wipeup' => $this->t('Wipe up'),
        'wipedown' => $this->t('Wipe down'),
        'slideleft' => $this->t('Slide left'),
        'slideright' => $this->t('Slide right'),
        'slideup' => $this->t('Slide up'),
        'slidedown' => $this->t('Slide down'),
        'circlecrop' => $this->t('Circle crop'),
        'rectcrop' => $this->t('Rectangle crop'),
        'circleopen' => $this->t('Circle open'),
        'circleclose' => $this->t('Circle close'),
        'horzclose' => $this->t('Horizontal close'),
        'horzopen' => $this->t('Horizontal open'),
        'vertclose' => $this->t('Vertical close'),
        'vertopen' => $this->t('Vertical open'),
        'diagbl' => $this->t('Diagonal bottom-left'),
        'diagbr' => $this->t('Diagonal bottom-right'),
        'diagtl' => $this->t('Diagonal top-left'),
        'diagtr' => $this->t('Diagonal top-right'),
      ],
      '#default_value' => $configuration['transition_type'] ?? 'fade',
      '#description' => $this->t('Select the transition effect between images.'),
    ];

    $form['transition_settings']['transition_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Transition Duration (seconds)'),
      '#default_value' => $configuration['transition_duration'] ?? 1,
      '#min' => 0.1,
      '#max' => 5,
      '#step' => 0.1,
      '#description' => $this->t('Duration of the transition effect in seconds.'),
    ];


    // Advanced settings.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['advanced']['bitrate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Video Bitrate'),
      '#default_value' => $configuration['bitrate'] ?? '1500k',
      '#description' => $this->t('The bitrate of the video (e.g., 1500k).'),
    ];

    $form['advanced']['framerate'] = [
      '#type' => 'number',
      '#title' => $this->t('Frame Rate'),
      '#default_value' => $configuration['framerate'] ?? 24,
      '#min' => 1,
      '#max' => 60,
      '#description' => $this->t('The frame rate of the video (frames per second).'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    return [
      'video_quality' => $form_state->getValue('video_quality'),
      'output_format' => $form_state->getValue('output_format'),
      'transition_type' => $form_state->getValue('transition_type'),
      'transition_duration' => $form_state->getValue('transition_duration'),
      'bitrate' => $form_state->getValue('bitrate'),
      'framerate' => $form_state->getValue('framerate'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      // 1. Extract image and audio file information from context
      $imageFiles = $this->findImageFiles($context);
      $audioFileInfo = $this->findFileInfo($context, 'audio_content');

      if (empty($imageFiles)) {
        throw new \Exception("No images found in context. Make sure previous steps have output_type 'featured_image'.");
      }

      if (!$audioFileInfo) {
        throw new \Exception("Audio file information not found in context. Make sure a previous step has output_type 'audio_content'.");
      }

      // 2. Validate files exist
      $imageEntities = [];
      $imagePaths = [];
      foreach ($imageFiles as $index => $imageFile) {
        $file = $this->entityTypeManager->getStorage('file')->load($imageFile['file_id']);
        if (!$file) {
          throw new \Exception("Image file not found with ID: " . $imageFile['file_id']);
        }
        $imageEntities[$index] = $file;
        $imagePaths[$index] = \Drupal::service('file_system')->realpath($file->getFileUri());
      }

      $audioFile = $this->entityTypeManager->getStorage('file')->load($audioFileInfo['file_id']);
      if (!$audioFile) {
        throw new \Exception("Audio file not found with ID: " . $audioFileInfo['file_id']);
      }
      $audioFilePath = \Drupal::service('file_system')->realpath($audioFile->getFileUri());

      // 3. Prepare output directory and filename
      $directory = 'private://pipeline/videos/' . date('Y-m');
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      $outputFormat = $config['configuration']['output_format'] ?? 'mp4';
      $filename = uniqid('video_', true) . '.' . $outputFormat;
      $outputUri = $directory . '/' . $filename;

      // Using system temp directory for FFmpeg output
      $tempDirectory = '/tmp/pipeline_videos';
      if (!file_exists($tempDirectory)) {
        mkdir($tempDirectory, 0755, true);
      }

      $tempFilename = 'video_temp_' . uniqid() . '.' . $outputFormat;
      $outputFilePath = $tempDirectory . '/' . $tempFilename;

      // Get audio duration
      $durationCommand = "ffprobe -i " . escapeshellarg($audioFilePath) . " -show_entries format=duration -v quiet -of csv=\"p=0\"";
      $duration = trim(shell_exec($durationCommand));

      if (!is_numeric($duration)) {
        throw new \Exception("Failed to determine audio duration: " . $duration);
      }

      // Calculate total duration of all images
      $totalImageDuration = 0;
      foreach ($imageFiles as $imageFile) {
        $totalImageDuration += $imageFile['video_settings']['duration'];
      }

      // 4. Build the FFmpeg command for multiple images
      $ffmpegCommand = $this->buildMultiImageFFmpegCommand(
        $imagePaths,
        $imageFiles,
        $audioFilePath,
        $outputFilePath,
        $config['configuration'],
        $context
      );

      // 5. Log the command for debugging
      $this->loggerFactory->get('pipeline')->notice('Executing FFmpeg command: @command', ['@command' => $ffmpegCommand]);

      // 6. Execute FFmpeg and capture output
      $outputAndErrors = [];
      $returnCode = 0;
      exec($ffmpegCommand . " 2>&1", $outputAndErrors, $returnCode);

      if ($returnCode !== 0) {
        throw new \Exception("FFmpeg execution failed: " . implode("\n", $outputAndErrors));
      }

      // 7. Check if the file was created
      if (!file_exists($outputFilePath)) {
        throw new \Exception("FFmpeg did not create an output file.");
      }

      // 8. Create and save file entity
      $file = $this->fileRepository->writeData(
        file_get_contents($outputFilePath),
        $outputUri,
        FileExists::Replace
      );

      if (!$file) {
        throw new \Exception("Failed to save video file");
      }

      // Clean up temporary file
      unlink($outputFilePath);

      $file->setPermanent();
      $file->save();

      // 9. Create media entity
      $mediaId = $this->createMediaEntity($file);

      // 10. Return video information with slide data
      return json_encode([
        'file_id' => $file->id(),
        'media_id' => $mediaId,
        'uri' => $file->getFileUri(),
        'url' => $file->createFileUrl(),
        'mime_type' => 'video/' . $outputFormat,
        'filename' => $filename,
        'duration' => (float) $duration,
        'size' => $file->getSize(),
        'timestamp' => \Drupal::time()->getCurrentTime(),
        'slides' => array_map(function($img) {
          return [
            'file_id' => $img['file_id'],
            'duration' => $img['video_settings']['duration'],
          ];
        }, $imageFiles),
      ]);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error generating video: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Builds FFmpeg command for multiple image slideshow with transitions and text overlays.
   *
   * @param array $imagePaths
   *   Array of image file paths.
   * @param array $imageFiles
   *   Array of image file information.
   * @param string $audioPath
   *   Path to the audio file.
   * @param string $outputPath
   *   Path where the output video should be saved.
   * @param array $config
   *   Configuration options.
   * @param array $context
   *   The pipeline context containing step results.
   *
   * @return string
   *   The FFmpeg command.
   */
  protected function buildMultiImageFFmpegCommand(
    array $imagePaths,
    array $imageFiles,
    string $audioPath,
    string $outputPath,
    array $config = [],
    array $context = []
  ): string {
    // Get configuration
    $transitionType = $config['transition_type'] ?? 'fade';
    $transitionDuration = $config['transition_duration'] ?? 1;
    $resolution = $this->getResolution($config['video_quality'] ?? 'medium');
    $bitrate = $config['bitrate'] ?? '1500k';
    $framerate = $config['framerate'] ?? 24;

    // Build the ffmpeg command
    $ffmpegCmd = "ffmpeg";

    // Add input images
    foreach ($imagePaths as $index => $path) {
      $ffmpegCmd .= " -loop 1 -i " . escapeshellarg($path);
    }

    // Add audio input
    $ffmpegCmd .= " -i " . escapeshellarg($audioPath);

    // Start building filter complex
    $filterComplex = "";

    // Process each image - scale and apply text in a single filter chain per image
    for ($i = 0; $i < count($imagePaths); $i++) {
      // Start with scaling
      $filterComplex .= sprintf("[%d:v]scale=%s:force_divisible_by=2,setsar=1,format=yuv420p",
        $i, $resolution);

      // Check if image has text overlay configuration and it's enabled
      if (isset($imageFiles[$i]['text_overlay']) && !empty($imageFiles[$i]['text_overlay']['enabled'])
        && !empty($imageFiles[$i]['text_overlay']['text'])) {

        $textConfig = $imageFiles[$i]['text_overlay'];

        // Process text content to replace placeholders
        $text = $this->processTextContent($textConfig['text'], $context);
        $fontSize = !empty($textConfig['font_size']) ? $textConfig['font_size'] : 24;
        $fontColor = !empty($textConfig['font_color']) ? $textConfig['font_color'] : 'white';

        // Get position parameters
        $customCoords = ($textConfig['position'] === 'custom') ?
          ['x' => $textConfig['custom_x'], 'y' => $textConfig['custom_y']] : NULL;
        $position = $this->getTextPosition($textConfig['position'], $resolution, $customCoords);

        // Add background box if configured
        $boxParam = '';
        if (!empty($textConfig['background_color'])) {
          $boxParam = ":box=1:boxcolor={$textConfig['background_color']}:boxborderw=5";
        }

        // Chain the drawtext filter to the scaling
        $filterComplex .= sprintf(",drawtext=text='%s':fontsize=%d:fontcolor=%s:%s%s",
          addslashes($text),
          $fontSize,
          $fontColor,
          $position,
          $boxParam
        );
      }

      // Complete this image's filter chain
      $filterComplex .= sprintf("[v%d];", $i);
    }

    // Calculate durations
    $durations = [];
    $totalDuration = 0;
    for ($i = 0; $i < count($imageFiles); $i++) {
      $durations[$i] = $imageFiles[$i]['video_settings']['duration'] ?? 5;
      $totalDuration += $durations[$i];
    }

    // Add duration adjustment to match audio
    $audioDuration = $this->getAudioDuration($audioPath);
    $totalTransitionTime = ($transitionDuration * (count($imagePaths) - 1));

    // Log the timing calculations for debugging
    $this->loggerFactory->get('pipeline')->debug(
      sprintf("Timing details:\n- Audio duration: %.2f\n- Total image duration: %.2f\n- Transition time: %.2f",
        $audioDuration, $totalDuration, $totalTransitionTime)
    );

    // Adjust image durations to match audio duration
    if ($audioDuration > 0 && abs($totalDuration - $totalTransitionTime - $audioDuration) > 0.1) {
      $scaleFactor = $audioDuration / ($totalDuration - $totalTransitionTime);
      for ($i = 0; $i < count($durations); $i++) {
        $durations[$i] = $durations[$i] * $scaleFactor;
      }
      $this->loggerFactory->get('pipeline')->debug(
        sprintf("Adjusted durations: %s, Total after adjustment: %.2f",
          json_encode($durations), array_sum($durations))
      );
    }

    // First image
    $filterComplex .= sprintf("[v0]trim=duration=%s,setpts=PTS-STARTPTS[hold0];",
      $durations[0]);
    $lastOutput = "hold0";

    // Before the loop, initialize the offset
    $currentOffset = $durations[0] - $transitionDuration;

    // Process remaining images with transitions
    for ($i = 1; $i < count($imagePaths); $i++) {
      $filterComplex .= sprintf("[v%d]trim=duration=%s,setpts=PTS-STARTPTS[hold%d];",
        $i, $durations[$i], $i);

      $offsetTime = $currentOffset;
      if ($offsetTime < 0) $offsetTime = 0;

      $filterComplex .= sprintf("[%s][hold%d]xfade=transition=%s:duration=%s:offset=%s[trans%d];",
        $lastOutput, $i, $transitionType, $transitionDuration, $offsetTime, $i);

      $lastOutput = sprintf("trans%d", $i);

      // Update the offset for the next transition
      $currentOffset += $durations[$i] - $transitionDuration;
    }

    // Complete the command
    $ffmpegCmd .= " -filter_complex " . escapeshellarg($filterComplex);
    $ffmpegCmd .= " -map \"[" . $lastOutput . "]\" -map " . count($imagePaths) . ":a";
    $ffmpegCmd .= " -c:v libx264 -c:a aac -pix_fmt yuv420p";
    $ffmpegCmd .= " -r " . $framerate . " -b:v " . $bitrate;
    $ffmpegCmd .= " -shortest " . escapeshellarg($outputPath);
    $ffmpegCmd .= " -y";

    return $ffmpegCmd;
  }

  /**
   * Finds all image file information in the context.
   *
   * @param array $context
   *   The pipeline execution context.
   *
   * @return array
   *   An array of image file information.
   */
  protected function findImageFiles(array $context): array {
    $images = [];

    foreach ($context['results'] as $stepKey => $stepResult) {
      if (isset($stepResult['output_type']) && $stepResult['output_type'] === 'featured_image') {
        $data = $stepResult['data'];

        // If it's a JSON string, decode it
        if (is_string($data) && $this->isJson($data)) {
          $imageData = json_decode($data, TRUE);
          // Add the step key as an identifier
          $imageData['step_key'] = $stepKey;
          $images[] = $imageData;
        }
        // If it's already an array, add it
        elseif (is_array($data)) {
          $data['step_key'] = $stepKey;
          $images[] = $data;
        }
      }
    }

    return $images;
  }

  /**
   * Finds file information in the context based on output type.
   *
   * @param array $context
   *   The pipeline execution context.
   * @param string $outputType
   *   The output type to find.
   *
   * @return array|null
   *   The file information or null if not found.
   */
  protected function findFileInfo(array $context, string $outputType): ?array
  {
    foreach ($context['results'] as $stepResult) {
      if (isset($stepResult['output_type']) && $stepResult['output_type'] === $outputType) {
        $data = $stepResult['data'];

        // If it's a JSON string, decode it
        if (is_string($data) && $this->isJson($data)) {
          return json_decode($data, TRUE);
        }

        // If it's already an array, return it
        if (is_array($data)) {
          return $data;
        }
      }
    }

    return NULL;
  }

  /**
   * Creates a media entity for the generated video.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity representing the video.
   *
   * @return int|null
   *   The media entity ID if created successfully, null otherwise.
   */
  protected function createMediaEntity($file): ?int
  {
    try {
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => 'video',
        'name' => 'Generated Video: ' . $file->getFilename(),
        'field_media_video_file' => [
          'target_id' => $file->id(),
          'description' => 'AI-generated video',
        ],
        'status' => 1,
      ]);

      $media->save();
      return $media->id();
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error creating media entity for video: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Gets the exact duration of an audio file.
   *
   * @param string $audioPath
   *   Path to the audio file.
   *
   * @return float
   *   The duration in seconds.
   */
  protected function getAudioDuration($audioPath) {
    $durationCommand = "ffprobe -i " . escapeshellarg($audioPath) . " -show_entries format=duration -v quiet -of csv=\"p=0\"";
    $duration = trim(shell_exec($durationCommand));
    return is_numeric($duration) ? (float)$duration : 0;
  }

  /**
   * Gets the resolution based on the quality setting.
   *
   * @param string $quality
   *   The quality setting (low, medium, high).
   *
   * @return string
   *   The resolution string for FFmpeg.
   */
  protected function getResolution(string $quality): string
  {
    switch ($quality) {
      case 'low':
        return '640:480';
      case 'high':
        return '1920:1080';
      case 'medium':
      default:
        return '1280:720';
    }
  }

  /**
   * Gets FFmpeg drawtext position parameters based on the selected position.
   *
   * @param string $position
   *   The position identifier.
   * @param string $resolution
   *   The video resolution in format "width:height".
   * @param array|null $customCoords
   *   Optional custom coordinates.
   *
   * @return string
   *   FFmpeg position parameters.
   */
  protected function getTextPosition(string $position, string $resolution, array $customCoords = NULL): string {
    // Parse resolution to get width and height
    list($width, $height) = explode(':', $resolution);

    // Default margins
    $margin = 20;

    switch ($position) {
      case 'top':
        return "x=(w-text_w)/2:y=$margin";
      case 'bottom':
        return "x=(w-text_w)/2:y=h-text_h-$margin";
      case 'center':
        return "x=(w-text_w)/2:y=(h-text_h)/2";
      case 'top_left':
        return "x=$margin:y=$margin";
      case 'top_right':
        return "x=w-text_w-$margin:y=$margin";
      case 'bottom_left':
        return "x=$margin:y=h-text_h-$margin";
      case 'bottom_right':
        return "x=w-text_w-$margin:y=h-text_h-$margin";
      case 'custom':
        if ($customCoords && isset($customCoords['x']) && isset($customCoords['y'])) {
          return "x={$customCoords['x']}:y={$customCoords['y']}";
        }
        // Fall back to center if custom coords are invalid
        return "x=(w-text_w)/2:y=(h-text_h)/2";
    }

    // Default to bottom if position is not recognized
    return "x=(w-text_w)/2:y=h-text_h-$margin";
  }

  /**
   * Processes text content to replace placeholders with values from context.
   *
   * @param string $text
   *   The text with placeholders.
   * @param array $context
   *   The pipeline context with results.
   *
   * @return string
   *   The processed text with placeholders replaced.
   */
  protected function processTextContent(string $text, array $context): string {
    return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($context) {
      $key = $matches[1];
      if (isset($context['results'][$key])) {
        $result = $context['results'][$key];
        // If result is an array with data key, use that
        if (is_array($result) && isset($result['data'])) {
          if (is_string($result['data'])) {
            // Try to decode JSON if it's a JSON string
            $decoded = json_decode($result['data'], TRUE);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['data'])) {
              return $decoded['data'];
            }
            return $result['data'];
          }
          return json_encode($result['data']);
        }
        // Otherwise return as string
        return is_string($result) ? $result : json_encode($result);
      }
      return $matches[0]; // Return original placeholder if not found
    }, $text);
  }

  /**
   * Checks if a string is JSON.
   *
   * @param string $string
   *   The string to check.
   *
   * @return bool
   *   TRUE if the string is JSON, FALSE otherwise.
   */
  protected function isJson(string $string): bool
  {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }
}
