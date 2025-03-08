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
  /**
   * Builds FFmpeg command for multiple image slideshow with transitions and text blocks.
   */
  public function buildMultiImageCommand(
    array  $imagePaths,
    array  $imageFiles,
    string $audioPath,
    string $outputPath,
    array  $config = [],
    array  $context = []
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

      // Check for text blocks first (new structure)
      if (isset($imageFiles[$i]['text_blocks']) && !empty($imageFiles[$i]['text_blocks'])) {
        // Collect enabled blocks
        $enabledBlocks = [];
        foreach ($imageFiles[$i]['text_blocks'] as $block) {
          if (!empty($block['enabled'])) {
            $enabledBlocks[] = $block;
          }
        }

// Process each enabled block with adjusted positions
        foreach ($enabledBlocks as $index => $block) {
          // Process text content to replace placeholders
          $text = $this->processTextContent($block['text'], $context);

          // Create a copy of the block to adjust if needed
          $adjustedBlock = $block;

          // Make adjustments based on the original position
          switch ($block['position']) {
            case 'top':
              // For top blocks, use intended position for first one,
              // then stack downward for additional blocks
              if ($index > 0) {
                $adjustedBlock['position'] = 'custom';
                $adjustedBlock['custom_x'] = (int)($resolution / 2); // center horizontally
                $adjustedBlock['custom_y'] = 20 + ($index * 50); // stack vertically
              }
              break;

            case 'center':
              // For center blocks, use intended position for first one,
              // then offset others vertically
              if ($index > 0) {
                $adjustedBlock['position'] = 'custom';
                $adjustedBlock['custom_x'] = (int)($resolution / 2); // center horizontally
                // Offset from center - upward for odd indices, downward for even
                $direction = ($index % 2 == 0) ? 1 : -1;
                $offset = ceil($index / 2) * 50;
                $adjustedBlock['custom_y'] = (int)($resolution / 2) + ($direction * $offset);
              }
              break;

            case 'bottom':
              // For bottom blocks, use intended position for first one,
              // then stack upward for additional blocks
              if ($index > 0) {
                $adjustedBlock['position'] = 'custom';
                $adjustedBlock['custom_x'] = (int)($resolution / 2); // center horizontally
                // Stack upward from bottom
                $yPos = (int)$resolution - 20 - ($index * 50);
                $adjustedBlock['custom_y'] = $yPos;
              }
              break;

            case 'top_left':
            case 'top_right':
            case 'bottom_left':
            case 'bottom_right':
            case 'left':
            case 'right':
              // For corner positions, use intended position for first one,
              // then offset slightly for others
              if ($index > 0) {
                $adjustedBlock['position'] = 'custom';

                // Determine base position based on original position
                switch ($block['position']) {
                  case 'top_left':
                    $adjustedBlock['custom_x'] = 20;
                    $adjustedBlock['custom_y'] = 20 + ($index * 40);
                    break;
                  case 'top_right':
                    $adjustedBlock['custom_x'] = (int)$resolution - 20;
                    $adjustedBlock['custom_y'] = 20 + ($index * 40);
                    break;
                  case 'bottom_left':
                    $adjustedBlock['custom_x'] = 20;
                    $adjustedBlock['custom_y'] = (int)$resolution - 20 - ($index * 40);
                    break;
                  case 'bottom_right':
                    $adjustedBlock['custom_x'] = (int)$resolution - 20;
                    $adjustedBlock['custom_y'] = (int)$resolution - 20 - ($index * 40);
                    break;
                  case 'left':
                    $adjustedBlock['custom_x'] = 20;
                    $adjustedBlock['custom_y'] = (int)($resolution / 2) + (($index % 2 == 0 ? 1 : -1) * ($index * 30));
                    break;
                  case 'right':
                    $adjustedBlock['custom_x'] = (int)$resolution - 20;
                    $adjustedBlock['custom_y'] = (int)($resolution / 2) + (($index % 2 == 0 ? 1 : -1) * ($index * 30));
                    break;
                }
              }
              break;

            case 'custom':
              // For custom positions, offset slightly if there are more than one
              if ($index > 0) {
                // Keep original X and increment Y
                $adjustedBlock['custom_y'] = ($block['custom_y'] ?? 0) + ($index * 40);
              }
              break;
          }

          // Build drawtext parameters for this block
          $drawTextParams = $this->buildDrawTextParameters($adjustedBlock, $resolution, $text);

          // Chain the drawtext filter to the current filter chain
          $filterComplex .= ',' . $drawTextParams;
        }
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
   * Builds the drawtext parameters for a text block.
   *
   * @param array $block
   *   The text block configuration.
   * @param string $resolution
   *   The video resolution.
   * @param string $text
   *   The processed text content.
   *
   * @return string
   *   The drawtext filter parameters.
   */
  /**
   * Builds the drawtext parameters for a text block.
   *
   * @param array $block
   *   The text block configuration.
   * @param string $resolution
   *   The video resolution.
   * @param string $text
   *   The processed text content.
   *
   * @return string
   *   The drawtext filter parameters.
   */
  public function buildDrawTextParameters(array $block, string $resolution, string $text): string {
    $fontSize = !empty($block['font_size']) ? $block['font_size'] : 24;
    $fontColor = !empty($block['font_color']) ? $block['font_color'] : 'white';

    // Completely escape text for FFmpeg's filter syntax
    // This is the most critical part for handling all special characters
    $escapedText = $this->escapeFFmpegText($text);

    // Get position parameters
    $position = '';
    if ($block['position'] === 'custom') {
      // Use custom coordinates directly
      $x = $block['custom_x'] ?? 0;
      $y = $block['custom_y'] ?? 0;
      $position = "x=$x:y=$y";
    } else {
      // Use predefined positions
      switch ($block['position']) {
        case 'top_left':
          $position = "x=20:y=20";
          break;
        case 'top':
          $position = "x=(w-text_w)/2:y=20";
          break;
        case 'top_right':
          $position = "x=w-text_w-20:y=20";
          break;
        case 'left':
          $position = "x=20:y=(h-text_h)/2";
          break;
        case 'center':
          $position = "x=(w-text_w)/2:y=(h-text_h)/2";
          break;
        case 'right':
          $position = "x=w-text_w-20:y=(h-text_h)/2";
          break;
        case 'bottom_left':
          $position = "x=20:y=h-text_h-20";
          break;
        case 'bottom':
          $position = "x=(w-text_w)/2:y=h-text_h-20";
          break;
        case 'bottom_right':
          $position = "x=w-text_w-20:y=h-text_h-20";
          break;
        default:
          $position = "x=(w-text_w)/2:y=(h-text_h)/2"; // Default to center
      }
    }

    // Add background box if configured
    $boxParam = '';
    if (!empty($block['background_color'])) {
      // Convert rgba() format to FFmpeg hex color format
      $bgColor = $this->convertToFFmpegColor($block['background_color']);
      $boxParam = ":box=1:boxcolor=$bgColor:boxborderw=5";
    }

    // Return the full drawtext parameter string
    return sprintf("drawtext=text='%s':fontsize=%d:fontcolor=%s:%s%s",
      $escapedText,
      $fontSize,
      $fontColor,
      $position,
      $boxParam
    );
  }

  /**
   * Escapes text for FFmpeg filter_complex parameter.
   *
   * This handles all special characters that could break the filter syntax.
   *
   * @param string $text
   *   The original text.
   *
   * @return string
   *   The text properly escaped for FFmpeg.
   */
  protected function escapeFFmpegText($text) {
    // First, escape backslashes
    $text = str_replace('\\', '\\\\', $text);

    // Escape single quotes (this is crucial for the filter_complex syntax)
    $text = str_replace("'", "'\\\\\\'", $text);

    // Escape other special characters that might break the filter
    $special_chars = [':', ',', '[', ']', ';', '=', '%', '-', '+'];
    foreach ($special_chars as $char) {
      $text = str_replace($char, '\\' . $char, $text);
    }

    return $text;
  }

  /**
   * Converts various color formats to FFmpeg compatible color format.
   *
   * @param string $color
   *   The color in various formats (name, hex, rgba).
   *
   * @return string
   *   FFmpeg compatible color string.
   */
  protected function convertToFFmpegColor($color) {
    // Check if it's rgba format
    if (preg_match('/rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d\.]+)\s*\)/i', $color, $matches)) {
      list(, $r, $g, $b, $a) = $matches;
      // Convert alpha from 0-1 to 0-255
      $alpha = round($a * 255);
      // Format as 0xRRGGBBAA
      return sprintf('0x%02x%02x%02x%02x', $r, $g, $b, $alpha);
    }

    // Check if it's rgb format
    if (preg_match('/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', $color, $matches)) {
      list(, $r, $g, $b) = $matches;
      // Format as 0xRRGGBBAA (fully opaque)
      return sprintf('0x%02x%02x%02xff', $r, $g, $b);
    }

    // Check if it's a hex color
    if (preg_match('/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i', $color, $matches)) {
      list(, $r, $g, $b) = $matches;
      // Format as 0xRRGGBBAA (fully opaque)
      return sprintf('0x%s%s%sff', $r, $g, $b);
    }

    // For named colors, just return as is (FFmpeg recognizes common color names)
    return $color;
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
