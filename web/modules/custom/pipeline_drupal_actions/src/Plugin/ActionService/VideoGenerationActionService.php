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
      'bitrate' => $form_state->getValue(['advanced', 'bitrate']),
      'framerate' => $form_state->getValue(['advanced', 'framerate']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      // 1. Extract image and audio file information from context
      $imageFileInfo = $this->findFileInfo($context, 'featured_image');
      $audioFileInfo = $this->findFileInfo($context, 'audio_content');

      if (!$imageFileInfo) {
        throw new \Exception("Image file information not found in context. Make sure a previous step has output_type 'featured_image'.");
      }

      if (!$audioFileInfo) {
        throw new \Exception("Audio file information not found in context. Make sure a previous step has output_type 'audio_content'.");
      }

      // 2. Validate files exist
      $imageFile = $this->entityTypeManager->getStorage('file')->load($imageFileInfo['file_id']);
      $audioFile = $this->entityTypeManager->getStorage('file')->load($audioFileInfo['file_id']);

      if (!$imageFile) {
        throw new \Exception("Image file not found with ID: " . $imageFileInfo['file_id']);
      }

      if (!$audioFile) {
        throw new \Exception("Audio file not found with ID: " . $audioFileInfo['file_id']);
      }

      // Get file system paths
      $imageFilePath = \Drupal::service('file_system')->realpath($imageFile->getFileUri());
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

      // Ensure image dimensions are compatible with video encoding
      $processedImagePath = $this->ensureCompatibleDimensions($imageFilePath, $tempDirectory);

      $tempFilename = 'video_temp_' . uniqid() . '.' . $outputFormat;
      $outputFilePath = $tempDirectory . '/' . $tempFilename;

      // Get audio duration
      $durationCommand = "ffprobe -i " . escapeshellarg($audioFilePath) . " -show_entries format=duration -v quiet -of csv=\"p=0\"";
      $duration = trim(shell_exec($durationCommand));

      if (!is_numeric($duration)) {
        throw new \Exception("Failed to determine audio duration: " . $duration);
      }

      // Build the FFmpeg command
      $ffmpegCommand = "ffmpeg";
      $ffmpegCommand .= " -loop 1";
      $ffmpegCommand .= " -i " . escapeshellarg($processedImagePath);
      $ffmpegCommand .= " -i " . escapeshellarg($audioFilePath);
      $ffmpegCommand .= " -c:v libx264";
      $ffmpegCommand .= " -c:a aac";
      $ffmpegCommand .= " -t " . $duration;
      $ffmpegCommand .= " -pix_fmt yuv420p";
      $ffmpegCommand .= " -shortest";
      $ffmpegCommand .= " " . escapeshellarg($outputFilePath);
      $ffmpegCommand .= " -y";

      // Log the command for debugging
      $this->loggerFactory->get('pipeline')->notice('Executing FFmpeg command: @command', ['@command' => $ffmpegCommand]);

      // Execute FFmpeg and capture output
      $outputAndErrors = [];
      $returnCode = 0;
      exec($ffmpegCommand . " 2>&1", $outputAndErrors, $returnCode);

      if ($returnCode !== 0) {
        throw new \Exception("FFmpeg execution failed: " . implode("\n", $outputAndErrors));
      }

      // Check if the file was created
      if (!file_exists($outputFilePath)) {
        throw new \Exception("FFmpeg did not create an output file.");
      }

      // 6. Create and save file entity
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

      // 7. Create media entity
      $mediaId = $this->createMediaEntity($file);

      // 8. Return video information
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
      ]);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error generating video: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
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

  /**
   * Ensures dimensions are compatible with video encoding requirements.
   *
   * @param string $imagePath
   *   Path to the image file.
   * @param string $tempDir
   *   Temporary directory for output.
   *
   * @return string
   *   Path to the processed image with compatible dimensions.
   */
  protected function ensureCompatibleDimensions($imagePath, $tempDir) {
    // Get image dimensions
    $command = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 " . escapeshellarg($imagePath);
    $dimensions = trim(shell_exec($command));

    // If dimensions couldn't be obtained or are already even, return original
    if (empty($dimensions)) {
      return $imagePath;
    }

    list($width, $height) = explode('x', $dimensions);
    $width = (int)$width;
    $height = (int)$height;

    // Check if both dimensions are even
    if ($width % 2 === 0 && $height % 2 === 0) {
      return $imagePath;
    }

    // Make dimensions even by reducing by 1 if needed
    $newWidth = $width % 2 === 0 ? $width : $width - 1;
    $newHeight = $height % 2 === 0 ? $height : $height - 1;

    // Create adjusted image
    $outputPath = $tempDir . '/adjusted_' . basename($imagePath);
    $resizeCommand = "ffmpeg -i " . escapeshellarg($imagePath) .
      " -vf scale=" . $newWidth . ":" . $newHeight .
      " -y " . escapeshellarg($outputPath);

    exec($resizeCommand, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($outputPath)) {
      // Log the error but return original path as fallback
      \Drupal::logger('pipeline')->error('Failed to resize image to even dimensions: @command', ['@command' => $resizeCommand]);
      return $imagePath;
    }

    return $outputPath;
  }
}
