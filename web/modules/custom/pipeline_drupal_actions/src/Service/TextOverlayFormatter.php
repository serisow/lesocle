<?php
namespace Drupal\pipeline_drupal_actions\Service;

/**
 * Service for formatting text overlays for video generation.
 */
class TextOverlayFormatter
{

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
   * Builds the full FFmpeg drawtext parameter string.
   *
   * @param array $textConfig
   *   The text overlay configuration.
   * @param string $resolution
   *   The video resolution.
   * @param string $text
   *   The text content after placeholder processing.
   *
   * @return string
   *   The complete drawtext filter parameter string.
   */
  public function buildDrawTextParameters(array $textConfig, string $resolution, string $text): string
  {
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

    // Return the full drawtext parameter string
    return sprintf("drawtext=text='%s':fontsize=%d:fontcolor=%s:%s%s",
      addslashes($text),
      $fontSize,
      $fontColor,
      $position,
      $boxParam
    );
  }

  /**
   * Validates text overlay configuration.
   *
   * @param array $textConfig
   *   The text overlay configuration.
   *
   * @return bool
   *   TRUE if the configuration is valid, FALSE otherwise.
   */
  public function validateTextOverlayConfig(array $textConfig): bool
  {
    // Check if text overlay is enabled and has text content
    if (empty($textConfig['enabled']) || empty($textConfig['text'])) {
      return FALSE;
    }

    // Check required fields
    $requiredFields = ['position', 'font_size', 'font_color'];
    foreach ($requiredFields as $field) {
      if (!isset($textConfig[$field])) {
        return FALSE;
      }
    }

    // If position is custom, check for coordinates
    if ($textConfig['position'] === 'custom') {
      if (!isset($textConfig['custom_x']) || !isset($textConfig['custom_y'])) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Escapes text for FFmpeg drawtext filter.
   *
   * @param string $text
   *   The text to escape.
   *
   * @return string
   *   The escaped text.
   */
  public function escapeText(string $text): string
  {
    // Escape single quotes and backslashes
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace("'", "\\'", $text);

    // Replace newlines with escaped newlines
    return str_replace("\n", "\\n", $text);
  }
}
