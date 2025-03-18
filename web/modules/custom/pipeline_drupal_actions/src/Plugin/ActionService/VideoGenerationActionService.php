<?php
namespace Drupal\pipeline_drupal_actions\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\pipeline_drupal_actions\Service\FFmpegService;
use Drupal\pipeline_drupal_actions\Service\MediaEntityCreator;
use Drupal\pipeline_drupal_actions\Service\VideoFileManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "video_generation",
 *   label = @Translation("Video Generation Action")
 * )
 */
class VideoGenerationActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {

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
   * The FFmpeg service.
   *
   * @var \Drupal\pipeline_drupal_actions\Service\FFmpegService
   */
  protected $ffmpegService;

  /**
   * The video file manager service.
   *
   * @var \Drupal\pipeline_drupal_actions\Service\VideoFileManager
   */
  protected $videoFileManager;


  /**
   * The media entity creator service.
   *
   * @var \Drupal\pipeline_drupal_actions\Service\MediaEntityCreator
   */
  protected $mediaEntityCreator;

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
   * @param \Drupal\pipeline_drupal_actions\Service\FFmpegService $ffmpeg_service
   *   The FFmpeg service.
   * @param \Drupal\pipeline_drupal_actions\Service\VideoFileManager $video_file_manager
   *   The video file manager service.
   * @param \Drupal\pipeline_drupal_actions\Service\MediaEntityCreator $media_entity_creator
   *   The media entity creator service.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FFmpegService $ffmpeg_service,
    VideoFileManager $video_file_manager,
    MediaEntityCreator $media_entity_creator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->ffmpegService = $ffmpeg_service;
    $this->videoFileManager = $video_file_manager;
    $this->mediaEntityCreator = $media_entity_creator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('pipeline_drupal_actions.ffmpeg_service'),
      $container->get('pipeline_drupal_actions.video_file_manager'),
      $container->get('pipeline_drupal_actions.media_entity_creator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration) {
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
    $form['orientation'] = [
      '#type' => 'select',
      '#title' => $this->t('Video Orientation'),
      '#options' => [
        'horizontal' => $this->t('Horizontal (Landscape)'),
        'vertical' => $this->t('Vertical (Portrait - for TikTok/Instagram)'),
      ],
      '#default_value' => $configuration['orientation'] ?? 'horizontal',
      '#description' => $this->t('Select the orientation of the generated video.'),
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

    // In VideoGenerationActionService::buildConfigurationForm()
    $form['ken_burns'] = [
      '#type' => 'details',
      '#title' => $this->t('Ken Burns Effect'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['ken_burns']['ken_burns_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Ken Burns Effect'),
      '#default_value' => $configuration['ken_burns']['ken_burns_enabled'] ?? TRUE,
      '#description' => $this->t('Add subtle pan and zoom to images for a more professional look.'),
    ];

    $form['ken_burns']['ken_burns_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Effect Style'),
      '#options' => [
        'zoom_in' => $this->t('Zoom In'),
        'zoom_out' => $this->t('Zoom Out'),
        'pan_left' => $this->t('Pan Left'),
        'pan_right' => $this->t('Pan Right'),
        'random' => $this->t('Random (varies by image)'),
      ],
      '#default_value' => $configuration['ken_burns']['ken_burns_style'] ?? 'random',
      '#description' => $this->t('Select the style of the Ken Burns effect.'),
      '#states' => [
        'visible' => [
          ':input[name="ken_burns[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['ken_burns']['ken_burns_intensity'] = [
      '#type' => 'select',
      '#title' => $this->t('Effect Intensity'),
      '#options' => [
        'subtle' => $this->t('Subtle'),
        'moderate' => $this->t('Moderate'),
        'strong' => $this->t('Strong'),
      ],
      '#default_value' => $configuration['ken_burns']['ken_burns_intensity'] ?? 'moderate',
      '#description' => $this->t('Control how pronounced the effect is.'),
      '#states' => [
        'visible' => [
          ':input[name="ken_burns[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    return [
      'video_quality' => $form_state->getValue('video_quality'),
      'orientation' => $form_state->getValue('orientation'),
      'output_format' => $form_state->getValue('output_format'),
      'transition_type' => $form_state->getValue('transition_type'),
      'transition_duration' => $form_state->getValue('transition_duration'),
      'ken_burns' => [
        'ken_burns_enabled' => $form_state->getValue('ken_burns_enabled'),
        'ken_burns_style' => $form_state->getValue('ken_burns_style'),
        'ken_burns_intensity' => $form_state->getValue('ken_burns_intensity'),
      ],
      'bitrate' => $form_state->getValue('bitrate'),
      'framerate' => $form_state->getValue('framerate'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      // Extract image and audio files from context
      $imageFiles = $this->findImageFiles($context);
      $audioFileInfo = $this->findFileInfo($context, 'audio_content');

      if (empty($imageFiles)) {
        throw new \Exception("No images found in context. Make sure previous steps have output_type 'featured_image'.");
      }

      if (!$audioFileInfo) {
        throw new \Exception("Audio file information not found in context. Make sure a previous step has output_type 'audio_content'.");
      }

      // Load files and prepare paths
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

      // Create temp directory and prepare output path
      $tempDirectory = $this->videoFileManager->createTempDirectory();
      $outputFormat = $config['configuration']['output_format'] ?? 'mp4';
      $tempFilename = 'video_temp_' . uniqid() . '.' . $outputFormat;
      $outputFilePath = $tempDirectory . '/' . $tempFilename;

      // Build and execute FFmpeg command
      $ffmpegCommand = $this->ffmpegService->buildMultiImageCommand(
        $imagePaths,
        $imageFiles,
        $audioFilePath,
        $outputFilePath,
        $config['configuration'],
        $context
      );

      $result = $this->ffmpegService->executeCommand($ffmpegCommand);

      if ($result['returnCode'] !== 0) {
        throw new \Exception("FFmpeg execution failed: " . implode("\n", $result['output']));
      }

      if (!file_exists($outputFilePath)) {
        throw new \Exception("FFmpeg did not create an output file.");
      }

      // Save the file permanently
      $directory = 'private://pipeline/videos/' . date('Y-m');
      $file = $this->videoFileManager->createPermanentFile($outputFilePath, $directory, $outputFormat);

      // Create media entity
      $mediaId = $this->mediaEntityCreator->createMediaEntityFromFile($file, 'video');

      // Cleanup temporary files
      $this->videoFileManager->cleanupTempFiles([$outputFilePath]);

      // Get audio duration for the response
      $audioDuration = $this->ffmpegService->getAudioDuration($audioFilePath);

      // Return result
      return json_encode([
        'file_id' => $file->id(),
        'media_id' => $mediaId,
        'uri' => $file->getFileUri(),
        'url' => $file->createFileUrl(),
        'mime_type' => 'video/' . $outputFormat,
        'filename' => $file->getFilename(),
        'duration' => (float) $audioDuration,
        'size' => $file->getSize(),
        'timestamp' => \Drupal::time()->getCurrentTime(),
        'slides' => array_map(function($img) {
          return [
            'file_id' => $img['file_id'],
            'duration' => $img['video_settings']['duration'],
          ];
        }, $imageFiles),
      ]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error generating video: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
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
  protected function findFileInfo(array $context, string $outputType): ?array {
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
   * Checks if a string is JSON.
   *
   * @param string $string
   *   The string to check.
   *
   * @return bool
   *   TRUE if the string is JSON, FALSE otherwise.
   */
  protected function isJson(string $string): bool {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }
}
