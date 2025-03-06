<?php
namespace Drupal\pipeline_drupal_actions\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for FFmpeg video generation operations.
 */
class FFmpegService
{

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new FFmpegService.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory)
  {
    $this->loggerFactory = $logger_factory;
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
  public function buildMultiImageCommand(
    array  $imagePaths,
    array  $imageFiles,
    string $audioPath,
    string $outputPath,
    array  $config = [],
    array  $context = []
  ): string
  {
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
   * Gets the exact duration of an audio file.
   *
   * @param string $audioPath
   *   Path to the audio file.
   *
   * @return float
   *   The duration in seconds.
   */
  public function getAudioDuration($audioPath): float
  {
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
  public function getResolution(string $quality): string
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
  public function getTextPosition(string $position, string $resolution, array $customCoords = NULL): string
  {
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
  public function processTextContent(string $text, array $context): string
  {
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
   * Executes an FFmpeg command.
   *
   * @param string $command
   *   The FFmpeg command to execute.
   *
   * @return array
   *   An array containing the output and return code.
   */
  public function executeCommand(string $command): array
  {
    $this->loggerFactory->get('pipeline')->notice('Executing FFmpeg command: @command', ['@command' => $command]);

    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);

    return [
      'output' => $output,
      'returnCode' => $returnCode,
    ];
  }
}
