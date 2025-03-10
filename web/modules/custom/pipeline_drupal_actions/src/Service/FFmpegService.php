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
    // Parse resolution for width and height
    list($width, $height) = explode(':', $resolution);
    $width = (int)$width;
    $height = (int)$height;
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


    // Calculate durations
    $durations = [];
    $totalDuration = 0;
    for ($i = 0; $i < count($imageFiles); $i++) {
      $durations[$i] = isset($imageFiles[$i]['video_settings']['duration']) ?
        (float)$imageFiles[$i]['video_settings']['duration'] : 5.0;
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

    // Process each image - scale and apply text blocks
    for ($i = 0; $i < count($imagePaths); $i++) {
      // Start with scaling filter for this image
      if (!empty($config['ken_burns']['ken_burns_enabled'])) {
        // Get zoom/pan parameters based on configuration
        $kenBurnsParams = $this->getKenBurnsParameters(
          $config['ken_burns']['ken_burns_style'] ?? 'random',
          $config['ken_burns']['ken_burns_intensity'] ?? 'moderate',
          $i,
          $durations[$i] ?? 5,
          $framerate
        );

        // Apply zoompan filter after scaling
        $filterComplex .= sprintf("[%d:v]scale=%s:force_divisible_by=2,setsar=1,%s,format=yuv420p",
          $i, $resolution, $kenBurnsParams);
      } else {
        // Original code without Ken Burns
        $filterComplex .= sprintf("[%d:v]scale=%s:force_divisible_by=2,setsar=1,format=yuv420p",
          $i, $resolution);
      }

      // Calculate the start time for this slide in the overall video
      $slideStartTime = 0;
      for ($j = 0; $j < $i; $j++) {
        $slideStartTime += $durations[$j];
        // Subtract transition overlap
        if ($j > 0) {
          $slideStartTime -= $transitionDuration;
        }
      }
      $slideDuration = $durations[$i];

      // Get text blocks for this image
      if (isset($imageFiles[$i]['text_blocks']) && is_array($imageFiles[$i]['text_blocks'])) {
        foreach ($imageFiles[$i]['text_blocks'] as $block) {
          if (empty($block['enabled'])) continue;

          $text = $this->processTextContent($block['text'] ?? '', $context);
          if (empty($text)) continue;

          // Build drawtext parameters with animation support
          $drawTextParams = $this->buildDrawTextParameters(
            $block,
            "$width:$height",
            $text,
            $slideStartTime,
            $slideDuration
          );

          $filterComplex .= ',' . $drawTextParams;
        }
      }

      // Complete this image's filter chain
      $filterComplex .= sprintf("[v%d];", $i);
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
   * Counts the number of blocks with the same position.
   *
   * @param array $blocks
   *   The blocks to check.
   * @param string $position
   *   The position to count.
   *
   * @return int
   *   The number of blocks with the given position.
   */
  protected function countBlocksWithPosition(array $blocks, string $position): int {
    $count = 0;
    foreach ($blocks as $block) {
      if (isset($block['position']) && $block['position'] === $position) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Adjusts block position for multiple blocks with the same position.
   *
   * @param array $block
   *   The block to adjust.
   * @param int $width
   *   The video width.
   * @param int $height
   *   The video height.
   * @param int $index
   *   The index of the block.
   *
   * @return array
   *   The adjusted block.
   */
  protected function adjustBlockPositionForMultiple(array $block, int $width, int $height, int $index): array {
    $adjustedBlock = $block;
    $fontSize = $block['font_size'] ?? 24;
    $lineHeight = $fontSize * 1.5;
    $marginX = 20;
    $marginY = 20;
    $verticalSpacing = $lineHeight + 10;

    // Convert to custom position and adjust coordinates
    $adjustedBlock['position'] = 'custom';

    switch ($block['position']) {
      case 'top_left':
        $adjustedBlock['custom_x'] = $marginX;
        $adjustedBlock['custom_y'] = $marginY + ($index * $verticalSpacing);
        break;
      case 'top':
        $adjustedBlock['custom_x'] = $width / 2;
        $adjustedBlock['custom_y'] = $marginY + ($index * $verticalSpacing);
        break;
      case 'top_right':
        $adjustedBlock['custom_x'] = $width - $marginX;
        $adjustedBlock['custom_y'] = $marginY + ($index * $verticalSpacing);
        break;
      case 'left':
        $adjustedBlock['custom_x'] = $marginX;
        $adjustedBlock['custom_y'] = ($height / 2) + (($index % 2 == 0 ? 1 : -1) * ($index * $verticalSpacing / 2));
        break;
      case 'center':
        $adjustedBlock['custom_x'] = $width / 2;
        $adjustedBlock['custom_y'] = ($height / 2) + (($index % 2 == 0 ? 1 : -1) * ($index * $verticalSpacing / 2));
        break;
      case 'right':
        $adjustedBlock['custom_x'] = $width - $marginX;
        $adjustedBlock['custom_y'] = ($height / 2) + (($index % 2 == 0 ? 1 : -1) * ($index * $verticalSpacing / 2));
        break;
      case 'bottom_left':
        $adjustedBlock['custom_x'] = $marginX;
        $adjustedBlock['custom_y'] = $height - $marginY - ($index * $verticalSpacing);
        break;
      case 'bottom':
        $adjustedBlock['custom_x'] = $width / 2;
        $adjustedBlock['custom_y'] = $height - $marginY - ($index * $verticalSpacing);
        break;
      case 'bottom_right':
        $adjustedBlock['custom_x'] = $width - $marginX;
        $adjustedBlock['custom_y'] = $height - $marginY - ($index * $verticalSpacing);
        break;
    }

    return $adjustedBlock;
  }

  /**
   * Builds the drawtext parameters for a text block with animation support.
   *
   * @param array $block
   *   The text block configuration.
   * @param string $resolution
   *   The video resolution.
   * @param string $text
   *   The processed text content.
   * @param float $startTime
   *   The start time for this image within the video.
   * @param float $duration
   *   The duration this image is displayed.
   *
   * @return string
   *   The drawtext filter parameters.
   */

  /**
   * Builds the drawtext parameters for a text block with animation support.
   */
  public function buildDrawTextParameters(array $block, string $resolution, string $text, float $startTime = 0, float $duration = 5): string {
    $fontSize = !empty($block['font_size']) ? $block['font_size'] : 24;
    $fontColor = !empty($block['font_color']) ? $block['font_color'] : 'white';

    // Parse resolution
    list($width, $height) = explode(':', $resolution);
    $width = (int)$width;
    $height = (int)$height;

    // Properly escape text for FFmpeg - this is critical
    $escapedText = $this->escapeFFmpegText($text);

    // Fixed margin value
    $margin = 20;

    // Calculate position parameters
    $position = '';
    $posX = 0;
    $posY = 0;

    if ($block['position'] === 'custom' && isset($block['custom_x']) && isset($block['custom_y'])) {
      $posX = (int)$block['custom_x'];
      $posY = (int)$block['custom_y'];
      $position = "x=$posX:y=$posY";
    } else {
      // Standard positions
      switch ($block['position']) {
        case 'top_left':
          $position = "x=$margin:y=$margin";
          $posX = $margin;
          $posY = $margin;
          break;
        case 'top':
          $position = "x=(w-text_w)/2:y=$margin";
          $posX = $width / 2; // Approximate for calculations
          $posY = $margin;
          break;
        case 'top_right':
          $position = "x=w-text_w-$margin:y=$margin";
          $posX = $width - $margin;
          $posY = $margin;
          break;
        case 'left':
          $position = "x=$margin:y=(h-text_h)/2";
          $posX = $margin;
          $posY = $height / 2;
          break;
        case 'center':
          $position = "x=(w-text_w)/2:y=(h-text_h)/2";
          $posX = $width / 2;
          $posY = $height / 2;
          break;
        case 'right':
          $position = "x=w-text_w-$margin:y=(h-text_h)/2";
          $posX = $width - $margin;
          $posY = $height / 2;
          break;
        case 'bottom_left':
          $position = "x=$margin:y=h-text_h-$margin";
          $posX = $margin;
          $posY = $height - $margin;
          break;
        case 'bottom':
          $position = "x=(w-text_w)/2:y=h-text_h-$margin";
          $posX = $width / 2;
          $posY = $height - $margin;
          break;
        case 'bottom_right':
          $position = "x=w-text_w-$margin:y=h-text_h-$margin";
          $posX = $width - $margin;
          $posY = $height - $margin;
          break;
        default:
          $position = "x=(w-text_w)/2:y=(h-text_h)/2";
          $posX = $width / 2;
          $posY = $height / 2;
      }
    }

    // Add background box if configured
    $boxParam = '';
    if (!empty($block['background_color'])) {
      // Convert rgba() format to FFmpeg hex color format or use directly
      $bgColor = $this->convertToFFmpegColor($block['background_color']);
      $boxParam = ":box=1:boxcolor=$bgColor:boxborderw=5";
    }

    // Get animation parameters
    $animationType = isset($block['animation']['type']) ? $block['animation']['type'] : 'none';
    $animationDuration = isset($block['animation']['duration']) ? (float) $block['animation']['duration'] : 1.0;
    $animationDelay = isset($block['animation']['delay']) ? (float) $block['animation']['delay'] : 0.0;

    // Ensure animation duration isn't too long
    if ($animationDuration > $duration / 2) {
      $animationDuration = $duration / 2;
    }

    // Calculate timing for fade in/out
    $fadeInStart = $animationDelay;
    $fadeInEnd = $fadeInStart + $animationDuration;
    $fadeOutStart = $duration - $animationDuration;
    $fadeOutEnd = $duration;

    // Basic enable expression - show text during specified time period
    $enableExpr = "enable='between(t-$startTime,$fadeInStart,$fadeOutEnd)'";
    $additionalParams = '';

    // Apply animation based on type
    switch ($animationType) {
      case 'fade':
        // Simple fade in/out - using fixed values for reliability
        $additionalParams = sprintf(":alpha='if(lt(t-%.6f,%.6f),min(1,(t-%.6f-%.6f)/%.6f),if(gt(t-%.6f,%.6f),max(0,(1-(t-%.6f-%.6f)/%.6f)),1))'",
          $startTime, $fadeInEnd,
          $startTime, $fadeInStart, $animationDuration,
          $startTime, $fadeOutStart,
          $startTime, $fadeOutStart, $animationDuration
        );
        break;

      case 'slide':
        // Slide animation - simplified for reliability
        $slideDirection = $this->getSlideDirectionFromPosition($block['position']);
        $slideOffset = 100;

        if ($slideDirection === 'left' || $slideDirection === 'right') {
          $xValue = $posX;
          $slideExpr = '';

          if ($slideDirection === 'left') {
            // Slide from left
            $slideExpr = sprintf("if(lt(t-%.6f,%.6f),(%.6f-%.6f+(%.6f*(t-%.6f-%.6f)/%.6f)),%.6f)",
              $startTime, $fadeInEnd,
              $xValue, $slideOffset, $slideOffset,
              $startTime, $fadeInStart, $animationDuration,
              $xValue
            );
          } else {
            // Slide from right
            $slideExpr = sprintf("if(lt(t-%.6f,%.6f),(%.6f+%.6f-(%.6f*(t-%.6f-%.6f)/%.6f)),%.6f)",
              $startTime, $fadeInEnd,
              $xValue, $slideOffset, $slideOffset,
              $startTime, $fadeInStart, $animationDuration,
              $xValue
            );
          }
          $additionalParams = ":x='" . $slideExpr . "'";
        }
        else {
          $yValue = $posY;
          $slideExpr = '';

          if ($slideDirection === 'top') {
            // Slide from top
            $slideExpr = sprintf("if(lt(t-%.6f,%.6f),(%.6f-%.6f+(%.6f*(t-%.6f-%.6f)/%.6f)),%.6f)",
              $startTime, $fadeInEnd,
              $yValue, $slideOffset, $slideOffset,
              $startTime, $fadeInStart, $animationDuration,
              $yValue
            );
          } else {
            // Slide from bottom
            $slideExpr = sprintf("if(lt(t-%.6f,%.6f),(%.6f+%.6f-(%.6f*(t-%.6f-%.6f)/%.6f)),%.6f)",
              $startTime, $fadeInEnd,
              $yValue, $slideOffset, $slideOffset,
              $startTime, $fadeInStart, $animationDuration,
              $yValue
            );
          }
          $additionalParams = ":y='" . $slideExpr . "'";
        }

        // Add fade effect for smoother appearance
        $fadeExpr = sprintf("if(lt(t-%.6f,%.6f),min(1,(t-%.6f-%.6f)/%.6f),1)",
          $startTime, $fadeInEnd,
          $startTime, $fadeInStart, $animationDuration
        );
        $additionalParams .= ":alpha='" . $fadeExpr . "'";
        break;

      case 'scale':
        // Scale animation - grow from 0 to full size
        $scaleExpr = sprintf("if(lt(t-%.6f,%.6f),%.6f*(t-%.6f-%.6f)/%.6f,%.6f)",
          $startTime, $fadeInEnd,
          $fontSize,
          $startTime, $fadeInStart, $animationDuration,
          $fontSize
        );
        $additionalParams = ":fontsize='" . $scaleExpr . "'";

        // Add fade for smoother appearance
        $fadeExpr = sprintf("if(lt(t-%.6f,%.6f),min(1,(t-%.6f-%.6f)/%.6f),1)",
          $startTime, $fadeInEnd,
          $startTime, $fadeInStart, $animationDuration
        );
        $additionalParams .= ":alpha='" . $fadeExpr . "'";
        break;

      case 'typewriter':
        // Typewriter effect - text appears character by character
        $textLength = mb_strlen($text);
        $charPerSec = $textLength / max($animationDuration, 0.1); // Avoid division by zero

        $typewriterExpr = sprintf("if(lt(t-%.6f,%.6f),min(%d,floor(%.6f*(t-%.6f-%.6f))),%d)",
          $startTime, $fadeInEnd,
          $textLength, $charPerSec,
          $startTime, $fadeInStart,
          $textLength
        );
        $additionalParams = ":text='substr(\"$escapedText\",0," . $typewriterExpr . ")'";
        break;

      case 'none':
      default:
        // Simple fade in/out with fixed timing
        $additionalParams = sprintf(":alpha='if(lt(t-%.6f,%.6f),min(1,(t-%.6f-%.6f)/0.3),if(gt(t-%.6f,%.6f),max(0,(1-(t-%.6f-%.6f)/0.3)),1))'",
          $startTime, $fadeInStart + 0.3,
          $startTime, $fadeInStart,
          $startTime, $fadeOutEnd - 0.3,
          $startTime, $fadeOutEnd - 0.3
        );
        break;
    }

    // Return the full drawtext parameter string with animations
    return sprintf("drawtext=text='%s':fontsize=%d:fontcolor=%s:%s%s:%s%s",
      $escapedText,
      $fontSize,
      $fontColor,
      $position,
      $boxParam,
      $enableExpr,
      $additionalParams
    );
  }

  /**
   * Escapes text for FFmpeg filter_complex parameter.
   */
  protected function escapeFFmpegText($text) {
    // First, escape backslashes
    $text = str_replace('\\', '\\\\\\\\', $text);

    // Escape single quotes
    $text = str_replace("'", "\\\\'", $text);

    // Escape other special characters that might break the filter syntax
    $text = str_replace(':', '\\:', $text);
    $text = str_replace(',', '\\,', $text);
    $text = str_replace('[', '\\[', $text);
    $text = str_replace(']', '\\]', $text);
    $text = str_replace(';', '\\;', $text);
    $text = str_replace('=', '\\=', $text);

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

    // For named colors or already formatted colors, just return as is
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
   * Gets detailed position information for text based on its configuration.
   */
  protected function getTextPositionInfo(array $block, int $width, int $height, int $margin = 20): array {
    $result = [
      'position' => '',
      'x' => 0,
      'y' => 0
    ];

    if ($block['position'] === 'custom') {
      // Handle custom positions
      $x = isset($block['custom_x']) ? (int)$block['custom_x'] : 0;
      $y = isset($block['custom_y']) ? (int)$block['custom_y'] : 0;

      // If this appears to be a right-aligned position (x is close to right edge)
      if ($x > $width * 0.8) {
        // Use FFmpeg expression to place the right edge of text at the specified point
        $result['position'] = "x=min(w-text_w,$x):y=$y";
      } else {
        $result['position'] = "x=$x:y=$y";
      }
      $result['x'] = $x;
      $result['y'] = $y;
    } else {
      // Standard positions with proper right-alignment handling
      switch ($block['position']) {
        case 'top_left':
          $result['position'] = "x=$margin:y=$margin";
          $result['x'] = $margin;
          $result['y'] = $margin;
          break;
        case 'top':
          $result['position'] = "x=(w-text_w)/2:y=$margin";
          $result['x'] = $width / 2;
          $result['y'] = $margin;
          break;
        case 'top_right':
          $result['position'] = "x=w-text_w-$margin:y=$margin";
          $result['x'] = $width - $margin;
          $result['y'] = $margin;
          break;
        case 'left':
          $result['position'] = "x=$margin:y=(h-text_h)/2";
          $result['x'] = $margin;
          $result['y'] = $height / 2;
          break;
        case 'center':
          $result['position'] = "x=(w-text_w)/2:y=(h-text_h)/2";
          $result['x'] = $width / 2;
          $result['y'] = $height / 2;
          break;
        case 'right':
          $result['position'] = "x=w-text_w-$margin:y=(h-text_h)/2";
          $result['x'] = $width - $margin;
          $result['y'] = $height / 2;
          break;
        case 'bottom_left':
          $result['position'] = "x=$margin:y=h-text_h-$margin";
          $result['x'] = $margin;
          $result['y'] = $height - $margin;
          break;
        case 'bottom':
          $result['position'] = "x=(w-text_w)/2:y=h-text_h-$margin";
          $result['x'] = $width / 2;
          $result['y'] = $height - $margin;
          break;
        case 'bottom_right':
          $result['position'] = "x=w-text_w-$margin:y=h-text_h-$margin";
          $result['x'] = $width - $margin;
          $result['y'] = $height - $margin;
          break;
        default:
          $result['position'] = "x=(w-text_w)/2:y=(h-text_h)/2";
          $result['x'] = $width / 2;
          $result['y'] = $height / 2;
      }
    }

    return $result;
  }

  /**
   * Determines slide direction based on text position.
   */
  protected function getSlideDirectionFromPosition(string $position): string {
    switch ($position) {
      case 'top':
      case 'top_left':
      case 'top_right':
        return 'top';
      case 'bottom':
      case 'bottom_left':
      case 'bottom_right':
        return 'bottom';
      case 'left':
        return 'left';
      case 'right':
        return 'right';
      case 'center':
      default:
        // For center position, default to bottom
        return 'bottom';
    }
  }


  /**
   * Returns the easing function expression for FFmpeg.
   */
  protected function getEasingFunction(string $easing): string {
    switch ($easing) {
      case 'ease-in':
        return 'pow'; // t^2
      case 'ease-out':
        return 'sqrt'; // √t
      case 'ease-in-out':
        return '(sin((t-0.5)*PI)+1)/2'; // Sinusoidal easing
      case 'linear':
      default:
        return ''; // Linear (no function applied)
    }
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

  /**
   * Generates zoompan parameters for Ken Burns effect.
   *
   * @param string $style
   *   The style of Ken Burns effect.
   * @param string $intensity
   *   The intensity level of the effect.
   * @param int $imageIndex
   *   The index of the image being processed.
   * @param float $duration
   *   The duration of the image in seconds.
   * @param int $framerate
   *   The frame rate of the video.
   *
   * @return string
   *   FFmpeg zoompan filter parameters.
   */
  protected function getKenBurnsParameters(string $style, string $intensity, int $imageIndex, float $duration, int $framerate): string {
    // Convert duration to frames
    $frames = round($duration * $framerate);

    // Set zoom speed based on intensity
    $zoomSpeed = [
      'subtle' => 0.0005,
      'moderate' => 0.001,
      'strong' => 0.002,
    ][$intensity] ?? 0.001;

    // Set pan speed based on intensity (pixels per frame)
    $panSpeed = [
      'subtle' => 0.5,
      'moderate' => 0.8,
      'strong' => 1.2,
    ][$intensity] ?? 0.8;

    // Determine effect direction based on style or random
    if ($style === 'random') {
      $styles = ['zoom_in', 'zoom_out', 'pan_left', 'pan_right'];
      $style = $styles[$imageIndex % count($styles)];
    }

    // Build the appropriate zoompan parameters
    switch ($style) {
      case 'zoom_in':
        return sprintf('zoompan=z=\'min(1.0+%f*on,1.3)\':d=%d:x=\'iw/2-(iw/zoom/2)\':y=\'ih/2-(ih/zoom/2)\'',
          $zoomSpeed, $frames);

      case 'zoom_out':
        return sprintf('zoompan=z=\'max(1.3-%f*on,1.0)\':d=%d:x=\'iw/2-(iw/zoom/2)\':y=\'ih/2-(ih/zoom/2)\'',
          $zoomSpeed, $frames);

      case 'pan_left':
        return sprintf('zoompan=z=\'1.1\':d=%d:x=\'if(lte(on,%d),0,min(iw-(iw/zoom),%f*on))\':y=\'ih/2-(ih/zoom/2)\'',
          $frames, $frames/10, $panSpeed);

      case 'pan_right':
        return sprintf('zoompan=z=\'1.1\':d=%d:x=\'if(lte(on,%d),iw-(iw/zoom),max(0,iw-(iw/zoom)-%f*on))\':y=\'ih/2-(ih/zoom/2)\'',
          $frames, $frames/10, $panSpeed);

      default:
        // Default to zoom in if unknown style
        return sprintf('zoompan=z=\'min(1.0+%f*on,1.3)\':d=%d:x=\'iw/2-(iw/zoom/2)\':y=\'ih/2-(ih/zoom/2)\'',
          $zoomSpeed, $frames);
    }
  }
}
