<?php
namespace Drupal\pipeline_integration\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\pipeline\Service\FontService;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The font service.
   *
   * @var \Drupal\pipeline\Service\FontService
   */
  protected $fontService;

  /**
   * Constructs a new FFmpegService.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\pipeline\Service\FontService
   *   The font service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, FontService $font_service) {
    $this->loggerFactory = $logger_factory;
    $this->fontService = $font_service;
  }
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('pipeline.font_service')
    );
  }

  /**
   * Builds FFmpeg command for multiple image slideshow with transitions and text blocks.
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
  ): string {
    // Extract configuration options
    $transitionType = $config['transition_type'] ?? 'fade';
    $transitionDuration = $config['transition_duration'] ?? 1;
    $isVertical = ($config['orientation'] ?? 'horizontal') === 'vertical';
    $resolution = $this->getResolution($config['video_quality'] ?? 'medium', $isVertical);
    list($width, $height) = explode(':', $resolution);
    $width = (int)$width;
    $height = (int)$height;
    $bitrate = $config['bitrate'] ?? '1500k';
    $framerate = $config['framerate'] ?? 24;

    // Build base command (input files and audio)
    $ffmpegCmd = $this->buildBaseCommand($imagePaths, $audioPath);

    // Calculate durations and handle timing
    $durations = $this->calculateImageDurations($imageFiles, $audioPath, $transitionDuration);

    // Build filter complex components
    $filterComplex = $this->buildImageFiltersWithText(
      $imagePaths,
      $imageFiles,
      $durations,
      $width,
      $height,
      $resolution,
      $config,
      $context,
      $framerate
    );

    // Add transition filters
    $lastOutput = $this->buildTransitionSequence(
      $filterComplex,
      count($imagePaths),
      $durations,
      $transitionType,
      $transitionDuration
    );

    // Complete and return the command
    return $this->finalizeCommand(
      $ffmpegCmd,
      $filterComplex,
      $lastOutput,
      count($imagePaths),
      $outputPath,
      $framerate,
      $bitrate
    );
  }

  /**
   * Builds drawtext parameters using the local timeline of each input video.
   *
   * @param array $block
   *   The text block configuration.
   * @param string $resolution
   *   The video resolution.
   * @param string $text
   *   The processed text content.
   * @param float $slideDuration
   *   The duration of this slide.
   *
   * @return string
   *   The drawtext filter parameters.
   */
  protected function buildDrawTextWithLocalTimeline(
    array $block,
    string $resolution,
    string $text,
    float $slideDuration
  ): string {
    // Parse resolution
    list($width, $height) = explode(':', $resolution);
    $width = (int)$width;
    $height = (int)$height;

    // Properly escape text for FFmpeg
    $escapedText = $this->escapeFFmpegText($text);

    // Configure font parameters
    $fontParams = $this->configureFontParameters(
      $block['font_size'] ?? 24,
      $block['font_color'] ?? 'white',
      $block['font_family'] ?? 'default',
      $block['font_style'] ?? 'normal'
    );

    // Fixed margin for positioning
    $margin = 20;

    // Calculate position parameters
    list($posX, $posY, $positionParams) = $this->calculatePositionParameters(
      $block['position'] ?? 'center',
      $block['custom_x'] ?? 0,
      $block['custom_y'] ?? 0,
      $width,
      $height,
      $margin
    );

    // Add background box if configured
    $boxParams = $this->getBackgroundParameters($block['background_color'] ?? '');

    // Get animation type and timing parameters
    $animationType = $block['animation']['type'] ?? 'none';
    $animationDuration = $block['animation']['duration'] ?? 1.0;
    $animationDelay = $block['animation']['delay'] ?? 0.0;

    // Log the timing calculations
    $this->loggerFactory->get('pipeline')->debug(
      sprintf("Text block using LOCAL timeline (0-%0.2fs), animation=%s, delay=%0.2f",
        $slideDuration, $animationType, $animationDelay)
    );

    // Create enable expression for the full duration of this slide (in its local timeline)
    $enableExpr = sprintf("enable='between(t,0,%0.3f)'", $slideDuration);

    // Apply animation based on type
    $animationParams = $this->getAnimationParameters(
      $animationType,
      $animationDuration,
      $animationDelay,
      $slideDuration,
      $posX,
      $posY,
      $block['position'] ?? 'center',
      $block['font_size'] ?? 24,
      $text
    );

    // Return the full drawtext parameter string with animations
    return $this->formatFFmpegDrawText(
      $escapedText,
      $fontParams,
      $positionParams,
      $boxParams,
      $enableExpr,
      $animationParams
    );
  }

  /**
   * Configures font parameters for the drawtext filter.
   *
   * @param int $fontSize
   *   The font size.
   * @param string $fontColor
   *   The font color.
   * @param string $fontFamily
   *   The font family identifier.
   * @param string $fontStyle
   *   The font style (normal, outline, shadow, outline_shadow).
   *
   * @return string
   *   The font parameters for FFmpeg.
   */
  protected function configureFontParameters(int $fontSize, string $fontColor, string $fontFamily, string $fontStyle): string {
    // Get font file path if a specific font is selected
    $fontParam = '';
    if ($fontFamily !== 'default') {
      $fontFile = $this->fontService->getFontFilePath($fontFamily);
      if ($fontFile) {
        $fontParam = ":fontfile='" . $fontFile . "'";
      }
    }

    // Apply font styling based on the selected style
    $styleParams = '';
    switch ($fontStyle) {
      case 'outline':
        $styleParams = ":borderw=1.5:bordercolor=black";
        break;
      case 'shadow':
        $styleParams = ":shadowx=2:shadowy=2:shadowcolor=black";
        break;
      case 'outline_shadow':
        $styleParams = ":borderw=1.5:bordercolor=black:shadowx=2:shadowy=2:shadowcolor=black";
        break;
      case 'normal':
      default:
        // No additional styling
        break;
    }

    return "fontsize={$fontSize}:fontcolor={$fontColor}{$fontParam}{$styleParams}";
  }

  /**
   * Calculates position parameters for text placement.
   *
   * @param string $position
   *   The position identifier.
   * @param int $customX
   *   Custom X coordinate for custom positioning.
   * @param int $customY
   *   Custom Y coordinate for custom positioning.
   * @param int $width
   *   Video width.
   * @param int $height
   *   Video height.
   * @param int $margin
   *   Margin to use for positioning.
   *
   * @return array
   *   Array containing [posX, posY, positionParameter].
   */
  protected function calculatePositionParameters(
    string $position,
    int $customX,
    int $customY,
    int $width,
    int $height,
    int $margin
  ): array {
    $posX = 0;
    $posY = 0;
    $positionParam = '';

    if ($position === 'custom' && $customX !== 0 && $customY !== 0) {
      $posX = $customX;
      $posY = $customY;
      $positionParam = "x=$posX:y=$posY";
    } else {
      // Standard positions
      switch ($position) {
        case 'top_left':
          $positionParam = "x=$margin:y=$margin";
          $posX = $margin;
          $posY = $margin;
          break;
        case 'top':
          $positionParam = "x=(w-text_w)/2:y=$margin";
          $posX = $width / 2;
          $posY = $margin;
          break;
        case 'top_right':
          $positionParam = "x=w-text_w-$margin:y=$margin";
          $posX = $width - $margin;
          $posY = $margin;
          break;
        case 'left':
          $positionParam = "x=$margin:y=(h-text_h)/2";
          $posX = $margin;
          $posY = $height / 2;
          break;
        case 'center':
          $positionParam = "x=(w-text_w)/2:y=(h-text_h)/2";
          $posX = $width / 2;
          $posY = $height / 2;
          break;
        case 'right':
          $positionParam = "x=w-text_w-$margin:y=(h-text_h)/2";
          $posX = $width - $margin;
          $posY = $height / 2;
          break;
        case 'bottom_left':
          $positionParam = "x=$margin:y=h-text_h-$margin";
          $posX = $margin;
          $posY = $height - $margin;
          break;
        case 'bottom':
          $positionParam = "x=(w-text_w)/2:y=h-text_h-$margin";
          $posX = $width / 2;
          $posY = $height - $margin;
          break;
        case 'bottom_right':
          $positionParam = "x=w-text_w-$margin:y=h-text_h-$margin";
          $posX = $width - $margin;
          $posY = $height - $margin;
          break;
        default:
          $positionParam = "x=(w-text_w)/2:y=(h-text_h)/2";
          $posX = $width / 2;
          $posY = $height / 2;
      }
    }

    return [$posX, $posY, $positionParam];
  }

  /**
   * Gets background parameters for text background box.
   *
   * @param string $backgroundColor
   *   The background color.
   *
   * @return string
   *   The background box parameters.
   */
  protected function getBackgroundParameters(string $backgroundColor): string {
    if (empty($backgroundColor)) {
      return '';
    }

    $bgColor = $this->convertToFFmpegColor($backgroundColor);
    return ":box=1:boxcolor=$bgColor:boxborderw=5";
  }

  /**
   * Gets animation parameters based on animation type.
   *
   * @param string $animationType
   *   The type of animation.
   * @param float $animationDuration
   *   Duration of the animation in seconds.
   * @param float $animationDelay
   *   Delay before animation starts in seconds.
   * @param float $slideDuration
   *   Total duration of the slide in seconds.
   * @param int $posX
   *   X coordinate for positioning.
   * @param int $posY
   *   Y coordinate for positioning.
   * @param string $position
   *   Text position identifier.
   * @param int $fontSize
   *   Font size for scale animations.
   * @param string $originalText
   *   Original text for typewriter effect.
   *
   * @return string
   *   The animation parameters.
   */
  protected function getAnimationParameters(
    string $animationType,
    float $animationDuration,
    float $animationDelay,
    float $slideDuration,
    int $posX,
    int $posY,
    string $position,
    int $fontSize,
    string $originalText
  ): string {
    // CRITICAL FIX: Use local timeline (0 to slideDuration)
    $fadeInStart = $animationDelay;
    $fadeInEnd = $fadeInStart + $animationDuration;
    $fadeOutStart = $slideDuration - $animationDuration;
    $fadeOutEnd = $slideDuration;

    // Make sure animation stays within slide boundaries
    if ($fadeInEnd > $slideDuration) $fadeInEnd = $slideDuration;
    if ($fadeOutStart < $fadeInEnd) $fadeOutStart = $fadeInEnd;

    // Apply animation based on type
    switch ($animationType) {
      case 'fade':
        // Fade in at start, fade out at end
        return sprintf(":alpha='if(between(t,%0.3f,%0.3f),(t-%0.3f)/%0.3f,if(between(t,%0.3f,%0.3f),1-(t-%0.3f)/%0.3f,1))'",
          $fadeInStart, $fadeInEnd, // Fade in range
          $fadeInStart, $animationDuration, // Fade in calculation
          $fadeOutStart, $fadeOutEnd, // Fade out range
          $fadeOutStart, $animationDuration // Fade out calculation
        );

      case 'slide':
        $slideDirection = $this->getSlideDirectionFromPosition($position);
        $slideOffset = 100;
        $animParams = '';

        if ($slideDirection === 'left' || $slideDirection === 'right') {
          $xValue = $posX;

          if ($slideDirection === 'left') {
            // Slide from left with local timing
            $animParams = sprintf(":x='if(between(t,%0.3f,%0.3f),%0.3f+(%0.3f*(t-%0.3f)/%0.3f),%0.3f)'",
              $fadeInStart, $fadeInEnd, // Time range
              $xValue - $slideOffset, // Starting position
              $slideOffset, $fadeInStart, $animationDuration, // Movement calculation
              $xValue // Final position
            );
          } else {
            // Slide from right with local timing
            $animParams = sprintf(":x='if(between(t,%0.3f,%0.3f),%0.3f-(%0.3f*(t-%0.3f)/%0.3f),%0.3f)'",
              $fadeInStart, $fadeInEnd, // Time range
              $xValue + $slideOffset, // Starting position
              $slideOffset, $fadeInStart, $animationDuration, // Movement calculation
              $xValue // Final position
            );
          }
        } else {
          $yValue = $posY;

          if ($slideDirection === 'top') {
            // Slide from top with local timing
            $animParams = sprintf(":y='if(between(t,%0.3f,%0.3f),%0.3f+(%0.3f*(t-%0.3f)/%0.3f),%0.3f)'",
              $fadeInStart, $fadeInEnd, // Time range
              $yValue - $slideOffset, // Starting position
              $slideOffset, $fadeInStart, $animationDuration, // Movement calculation
              $yValue // Final position
            );
          } else {
            // Slide from bottom with local timing
            $animParams = sprintf(":y='if(between(t,%0.3f,%0.3f),%0.3f-(%0.3f*(t-%0.3f)/%0.3f),%0.3f)'",
              $fadeInStart, $fadeInEnd, // Time range
              $yValue + $slideOffset, // Starting position
              $slideOffset, $fadeInStart, $animationDuration, // Movement calculation
              $yValue // Final position
            );
          }
        }

        // Fade in for smoother appearance
        $animParams .= sprintf(":alpha='if(between(t,%0.3f,%0.3f),(t-%0.3f)/%0.3f,1)'",
          $fadeInStart, $fadeInEnd, // Range
          $fadeInStart, $animationDuration // Fade calculation
        );

        return $animParams;

      case 'scale':
        // Scale animation with local timing
        $scaleParams = sprintf(":fontsize='if(between(t,%0.3f,%0.3f),%0.3f*(t-%0.3f)/%0.3f,%0.3f)'",
          $fadeInStart, $fadeInEnd, // Time range
          $fontSize, // Target size
          $fadeInStart, $animationDuration, // Scaling calculation
          $fontSize // Final size
        );

        // Fade in for smoother appearance
        $scaleParams .= sprintf(":alpha='if(between(t,%0.3f,%0.3f),(t-%0.3f)/%0.3f,1)'",
          $fadeInStart, $fadeInEnd, // Range
          $fadeInStart, $animationDuration // Fade calculation
        );

        return $scaleParams;

      case 'typewriter':
        // Sanitize problematic characters for ffmpeg
        $safeText = str_replace(['@', ':', ';', ',', '\\'],
          ['[at]', ' ', ' ', ' ', ''],
          $originalText);

        // Simplified approach - just use fade-in animation for text that contains special characters
        return sprintf(":alpha='if(between(t,%0.3f,%0.3f),(t-%0.3f)/%0.3f,1)'",
          $fadeInStart, $fadeInEnd,
          $fadeInStart, $animationDuration
        );

      case 'none':
      default:
        // No additional animation
        return '';
    }
  }

  /**
   * Formats the complete drawtext filter parameters.
   *
   * @param string $escapedText
   *   The escaped text content.
   * @param string $fontParams
   *   Font configuration parameters.
   * @param string $positionParams
   *   Position parameters.
   * @param string $boxParams
   *   Background box parameters.
   * @param string $enableExpr
   *   Enable expression.
   * @param string $animationParams
   *   Animation parameters.
   *
   * @return string
   *   The complete drawtext filter parameters.
   */
  protected function formatFFmpegDrawText(
    string $escapedText,
    string $fontParams,
    string $positionParams,
    string $boxParams,
    string $enableExpr,
    string $animationParams
  ): string {
    return sprintf("drawtext=text='%s':%s:%s%s:%s%s",
      $escapedText,
      $fontParams,
      $positionParams,
      $boxParams,
      $enableExpr,
      $animationParams
    );
  }


  /**
   * Builds the base FFmpeg command with input files.
   *
   * @param array $imagePaths
   *   Array of image file paths.
   * @param string $audioPath
   *   Path to the audio file.
   *
   * @return string
   *   The base command.
   */
  protected function buildBaseCommand(array $imagePaths, string $audioPath): string {
    $ffmpegCmd = "ffmpeg";

    // Add input images
    foreach ($imagePaths as $index => $path) {
      $ffmpegCmd .= " -loop 1 -i " . escapeshellarg($path);
    }

    // Add audio input
    $ffmpegCmd .= " -i " . escapeshellarg($audioPath);

    return $ffmpegCmd;
  }

  /**
   * Calculates image durations adjusted to match audio.
   *
   * @param array $imageFiles
   *   Array of image file information.
   * @param string $audioPath
   *   Path to the audio file.
   * @param float $transitionDuration
   *   Duration of transitions between images.
   *
   * @return array
   *   Array of adjusted durations.
   */
  protected function calculateImageDurations(array $imageFiles, string $audioPath, float $transitionDuration): array {
    // Calculate initial durations from image settings
    $durations = [];
    $totalDuration = 0;

    for ($i = 0; $i < count($imageFiles); $i++) {
      $durations[$i] = isset($imageFiles[$i]['video_settings']['duration']) ?
        (float)$imageFiles[$i]['video_settings']['duration'] : 5.0;
      $totalDuration += $durations[$i];
    }

    // Calculate total transition time
    $totalTransitionTime = ($transitionDuration * (count($imageFiles) - 1));

    // Get audio duration
    $audioDuration = $this->getAudioDuration($audioPath);

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

    return $durations;
  }


/**
   * Builds image filters with text overlays.
   *
   * @param array $imagePaths
   *   Array of image file paths.
   * @param array $imageFiles
   *   Array of image file information.
   * @param array $durations
   *   Array of image durations.
   * @param int $width
   *   Video width (target canvas width).
   * @param int $height
   *   Video height (target canvas height).
   * @param string $resolution
   *   Video resolution string (informational).
   * @param array $config
   *   Configuration options.
   * @param array $context
   *   Pipeline context.
   * @param int $framerate
   *   Video frame rate.
   *
   * @return string
   *   The filter complex string for image processing.
   */
  protected function buildImageFiltersWithText(
    array $imagePaths,
    array $imageFiles,
    array $durations,
    int $width,
    int $height,
    string $resolution,
    array $config,
    array $context,
    int $framerate
  ): string {
    $filterComplex = "";

    // Process each image
    for ($i = 0; $i < count($imagePaths); $i++) {
      // Start building the filter chain string for this specific image
      $imageFilterChain = sprintf("[%d:v]", $i); // Input stream

      // --- START FINAL FIX - Scale(Increase) -> Crop -> KenBurns ---

      // 1. Scale the image to COVER the target dimensions, preserving aspect ratio.
      //    One dimension will match target, the other will overshoot.
      $imageFilterChain .= sprintf("scale=%d:%d:force_original_aspect_ratio=increase:force_divisible_by=2",
          $width, $height);

      // 2. Crop the scaled image back down to the exact target dimensions.
      //    Performs a center crop automatically.
      $imageFilterChain .= sprintf(",crop=%d:%d:(iw-ow)/2:(ih-oh)/2",
          $width, $height);

      // 3. Apply Ken Burns effect (if enabled) AFTER cropping.
      //    Operates on the correctly sized WxH frame.
      //    Still pass W/H and use s=WxH in getKenBurnsParameters for robustness.
      if (!empty($config['ken_burns']['ken_burns_enabled'])) {
        $kenBurnsParams = $this->getKenBurnsParameters(
          $config['ken_burns']['ken_burns_style'] ?? 'random',
          $config['ken_burns']['ken_burns_intensity'] ?? 'moderate',
          $i,
          $durations[$i] ?? 5,
          $framerate,
          $width,  // Pass target width
          $height  // Pass target height
        );
        // Append the Ken Burns filter
        $imageFilterChain .= "," . $kenBurnsParams;
      }

      // 4. Set SAR and Pixel Format (apply to the final geometry)
      $imageFilterChain .= ",setsar=1,format=yuv420p";

      // --- END FINAL FIX ---

      // 5. Add text overlays (operates on the final cropped/Ken Burns'd frame)
      $imageFilterChain = $this->addTextOverlays(
        $imageFilterChain, // Pass the chain built so far
        $imageFiles[$i] ?? [],
        $width,
        $height,
        $durations[$i],
        $context
      );

      // Append the completed filter chain for this image and label output [vX]
      $filterComplex .= $imageFilterChain . sprintf("[v%d];", $i);
    }

    // Add trim filters (remains the same)
    for ($i = 0; $i < count($imagePaths); $i++) {
      $filterComplex .= sprintf("[v%d]trim=duration=%s,setpts=PTS-STARTPTS[hold%d];", $i, $durations[$i], $i);
    }

    return $filterComplex;
  }

  /**
   * Adds text overlays to an image filter chain fragment.
   * (Modified slightly to directly append to the passed string)
   *
   * @param string $imageFilterChain
   *   Current filter chain string fragment for the image.
   * @param array $imageInfo
   *   Image information.
   * @param int $width
   *   Video width.
   * @param int $height
   *   Video height.
   * @param float $duration
   *   Image duration.
   * @param array $context
   *   Pipeline context.
   *
   * @return string
   *   Updated filter chain string fragment with text overlays appended.
   */
  protected function addTextOverlays(
    string $imageFilterChain, // Receive the current chain string
    array $imageInfo,
    int $width,
    int $height,
    float $duration,
    array $context
  ): string {
    // Check for text blocks and add them to the filter chain
    if (isset($imageInfo['text_blocks']) && is_array($imageInfo['text_blocks'])) {
      foreach ($imageInfo['text_blocks'] as $block) {
        if (empty($block['enabled'])) continue;

        $text = $this->processTextContent($block['text'] ?? '', $context);
        if (empty($text)) continue;

        // Build draw text parameters for this block
        $drawTextParams = $this->buildDrawTextWithLocalTimeline(
          $block,
          "$width:$height",
          $text,
          $duration
        );

        // Append the drawtext filter directly to the chain string
        $imageFilterChain .= ',' . $drawTextParams;
      }
    }
    // Return the modified chain string
    return $imageFilterChain;
  }
  
  /**
   * Builds transition sequence between images.
   *
   * @param string $filterComplex
   *   Current filter complex string.
   * @param int $imageCount
   *   Number of images.
   * @param array $durations
   *   Array of image durations.
   * @param string $transitionType
   *   Type of transition effect.
   * @param float $transitionDuration
   *   Duration of transitions.
   *
   * @return string
   *   Label of the last output stream.
   */
  protected function buildTransitionSequence(
    string &$filterComplex,
    int $imageCount,
    array $durations,
    string $transitionType,
    float $transitionDuration
  ): string {
    // First image becomes our initial output
    $lastOutput = "hold0";

    // Initialize offset for transitions
    $currentOffset = $durations[0] - $transitionDuration;

    // Process remaining images with transitions
    for ($i = 1; $i < $imageCount; $i++) {
      $offsetTime = max(0, $currentOffset);

      $filterComplex .= sprintf("[%s][hold%d]xfade=transition=%s:duration=%s:offset=%s[trans%d];",
        $lastOutput, $i, $transitionType, $transitionDuration, $offsetTime, $i);

      $lastOutput = sprintf("trans%d", $i);

      // Update the offset for the next transition
      $currentOffset += $durations[$i] - $transitionDuration;
    }

    return $lastOutput;
  }

  /**
   * Finalizes the FFmpeg command with mapping and encoding options.
   *
   * @param string $ffmpegCmd
   *   Base FFmpeg command.
   * @param string $filterComplex
   *   Filter complex string.
   * @param string $lastOutput
   *   Label of last output stream.
   * @param int $imageCount
   *   Number of images.
   * @param string $outputPath
   *   Output file path.
   * @param int $framerate
   *   Frame rate.
   * @param string $bitrate
   *   Video bitrate.
   *
   * @return string
   *   Complete FFmpeg command.
   */
  protected function finalizeCommand(
    string $ffmpegCmd,
    string $filterComplex,
    string $lastOutput,
    int $imageCount,
    string $outputPath,
    int $framerate,
    string $bitrate
  ): string {
    // Add filter complex
    $ffmpegCmd .= " -filter_complex " . escapeshellarg($filterComplex);

    // Add mapping
    $ffmpegCmd .= " -map \"[" . $lastOutput . "]\" -map " . $imageCount . ":a";

    // Add encoding options
    $ffmpegCmd .= " -c:v libx264 -c:a aac -pix_fmt yuv420p";
    $ffmpegCmd .= " -r " . $framerate . " -b:v " . $bitrate;

    // Add output options
    $ffmpegCmd .= " -shortest " . escapeshellarg($outputPath);
    $ffmpegCmd .= " -y";

    return $ffmpegCmd;
  }

  /**
   * Escapes text for FFmpeg filter_complex parameter.
   *
   * This function handles all special characters that could cause issues
   * in FFmpeg's filter_complex parameter.
   *
   * @param string $text
   *   Raw text to be escaped.
   *
   * @return string
   *   Properly escaped text for FFmpeg.
   */
  protected function escapeFFmpegText($text) {
    // First, unescape any already escaped double quotes to normalize the text
    $text = str_replace('\"', '"', $text);
    // Replace any literal backslashes first with QUADRUPLE backslashes
    // (this is because both shell and FFmpeg will interpret them)
    $text = str_replace('\\', '\\\\\\\\', $text);

    // Escape single quotes (critical for the shell command)
    $text = str_replace("'", "'\\\\\''", $text);

    // Replace problematic characters
    $replacements = [
      // Special characters in filter_complex
      ':' => '\\:',     // colon
      ',' => '\\,',     // comma
      ';' => '\\;',     // semicolon
      '=' => '\\=',     // equals
      '[' => '\\[',     // brackets
      ']' => '\\]',
      '?' => '\\?',     // question mark
      '!' => '\\!',     // exclamation
      '#' => '\\#',     // hash
      '$' => '\\$',     // dollar
      '%' => '\\\%',     // percentage
      '&' => '\\&',     // ampersand
      '(' => '\\(',     // parentheses
      ')' => '\\)',
      '*' => '\\*',     // asterisk
      '+' => '\\+',     // plus
      '/' => '\\/',     // slash
      '<' => '\\<',     // angle brackets
      '>' => '\\>',
      '@' => '\\@',     // at sign
      '^' => '\\^',     // caret
      '|' => '\\|',     // pipe
      '~' => '\\~',     // tilde
      '`' => '\\`',     // backtick
      '"' => '\\"',     // double quote
      ' '  => '\\ ',    // Space (for shell safety)
      '{'  => '\\{',    // Curly brace open (shell brace expansion, FFmpeg filter graphs)
      '}'  => '\\}',    // Curly brace close
      "\t" => '\\t',    // Tab character (for readability and FFmpeg compatibility)
      '.'  => '\\.',    // Dot (rarely an issue, but can be in some filter contexts)
    ];

    // Apply all replacements
    return str_replace(array_keys($replacements), array_values($replacements), $text);
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
  public function getResolution(string $quality, bool $vertical = false): string {
    // Standard horizontal resolutions
    $resolutions = [
      'low' => '640:480',
      'medium' => '1280:720',
      'high' => '1920:1080',
    ];

    // Vertical resolutions for social media
    $verticalResolutions = [
      'low' => '480:640',
      'medium' => '720:1280',
      'high' => '1080:1920', // Standard 9:16 for TikTok/Instagram
    ];

    return $vertical ? ($verticalResolutions[$quality] ?? $verticalResolutions['medium'])
      : ($resolutions[$quality] ?? $resolutions['medium']);
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
   * @param int $targetWidth   // <-- ADDED: Target video width
   * @param int $targetHeight  // <-- ADDED: Target video height
   *
   * @return string
   *   FFmpeg zoompan filter parameters.
   */
  protected function getKenBurnsParameters(
    string $style,
    string $intensity,
    int $imageIndex,
    float $duration,
    int $framerate,
    int $targetWidth,    // <-- ADDED
    int $targetHeight    // <-- ADDED
  ): string {
    // Convert duration to frames
    $frames = max(1, round($duration * $framerate)); // Ensure at least 1 frame

    // Zoom speed based on intensity
    $zoomSpeed = [
        'subtle' => 0.0005,
        'moderate' => 0.001,
        'strong' => 0.002,
    ][$intensity] ?? 0.001;

    // Pan speed based on intensity (relative to dimension per second -> adjusted for frame rate)
    // Let's define pan speed relative to the smaller dimension to be somewhat consistent
    $minDim = min($targetWidth, $targetHeight);
    $panSpeedFactor = [
        'subtle' => 0.05,  // e.g., 5% of the smaller dimension per second
        'moderate' => 0.1, // 10%
        'strong' => 0.15, // 15%
    ][$intensity] ?? 0.1;
    $panSpeedPixelsPerFrame = ($minDim * $panSpeedFactor) / $framerate;


    // Determine effect direction
    if ($style === 'random') {
        $styles = ['zoom_in', 'zoom_out', 'pan_left', 'pan_right', 'pan_up', 'pan_down']; // Added up/down
        // Simple pseudo-random selection based on index
        $style = $styles[($imageIndex + (int)($duration * 10)) % count($styles)];
    }

    // CRITICAL: Define the output size for zoompan
    $outputSize = sprintf("s=%dx%d", $targetWidth, $targetHeight);

    // Build the appropriate zoompan parameters
    // NOTE: x/y expressions calculate positioning based on the *input* dimensions (iw, ih)
    // but the effect is rendered onto the output size 's'.
    switch ($style) {
        case 'zoom_in':
            // Zoom in from 1.0 up to 1.2 (adjust max zoom if needed)
            return sprintf('zoompan=z=\'min(max(zoom,1.0)+%f,1.2)\':d=%d:x=\'iw/2-(iw/zoom/2)\':y=\'ih/2-(ih/zoom/2)\':%s',
                $zoomSpeed, $frames, $outputSize);

        case 'zoom_out':
            // Zoom out from 1.2 down to 1.0
            return sprintf('zoompan=z=\'max(1.2-%f*on,1.0)\':d=%d:x=\'iw/2-(iw/zoom/2)\':y=\'ih/2-(ih/zoom/2)\':%s',
                $zoomSpeed, $frames, $outputSize);

        case 'pan_left':
             // Start right, move left. Ensure x doesn't go below 0.
             // Start at x = max(0, iw-iw/zoom)
             $startX = 'max(0,iw-iw/zoom)';
             $moveX = sprintf('%f*on', $panSpeedPixelsPerFrame);
            return sprintf('zoompan=z=1.1:d=%d:x=\'max(0,%s-%s)\':y=\'ih/2-(ih/zoom/2)\':%s', // Use z=1.1 for slight zoom with pan
                 $frames, $startX, $moveX, $outputSize);

        case 'pan_right':
             // Start left, move right. Ensure x doesn't exceed iw-iw/zoom.
             // Start at x = 0
             $startX = '0';
             $maxX = 'iw-iw/zoom'; // Max x offset
             $moveX = sprintf('%f*on', $panSpeedPixelsPerFrame);
            return sprintf('zoompan=z=1.1:d=%d:x=\'min(%s,%s+%s)\':y=\'ih/2-(ih/zoom/2)\':%s',
                 $frames, $maxX, $startX, $moveX, $outputSize);

        case 'pan_up':
            // Start bottom, move up.
            $startY = 'max(0,ih-ih/zoom)';
            $moveY = sprintf('%f*on', $panSpeedPixelsPerFrame);
           return sprintf('zoompan=z=1.1:d=%d:x=\'iw/2-(iw/zoom/2)\':y=\'max(0,%s-%s)\':%s',
                $frames, $startY, $moveY, $outputSize);

        case 'pan_down':
            // Start top, move down.
            $startY = '0';
            $maxY = 'ih-ih/zoom';
            $moveY = sprintf('%f*on', $panSpeedPixelsPerFrame);
           return sprintf('zoompan=z=1.1:d=%d:x=\'iw/2-(iw/zoom/2)\':y=\'min(%s,%s+%s)\':%s',
                $frames, $maxY, $startY, $moveY, $outputSize);

        default: // Default to zoom_in
           return sprintf('zoompan=z=\'min(max(zoom,1.0)+%f,1.2)\':d=%d:x=\'iw/2-(iw/zoom/2)\':y=\'ih/2-(ih/zoom/2)\':%s',
                $zoomSpeed, $frames, $outputSize);
    }
  }

}
