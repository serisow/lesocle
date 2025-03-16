<?php
namespace Drupal\pipeline\Service;

/**
 * Service for font management operations.
 */
class FontService
{

  /**
   * Returns available fonts from system directories.
   *
   * @return array
   *   Array of available fonts with paths and metadata.
   */
  public function getAvailableFonts(): array
  {
    static $availableFonts = null;

    // Return cached result if already scanned
    if ($availableFonts !== null) {
      return $availableFonts;
    }

    $availableFonts = $this->scanAvailableFonts();
    return $availableFonts;
  }

  /**
   * Scans system directories for available fonts.
   *
   * @return array
   *   Array of available fonts with paths and metadata.
   */
  protected function scanAvailableFonts(): array
  {
    $fontDirs = [
      '/usr/share/fonts/dejavu',
      '/usr/share/fonts/opensans',
      '/usr/share/fonts/droid',
      '/usr/share/fonts/liberation',
      '/usr/share/fonts/freefont',
    ];

    $availableFonts = [];

    foreach ($fontDirs as $dir) {
      if (is_dir($dir)) {
        $files = glob($dir . '/*.ttf');
        foreach ($files as $file) {
          $fontName = basename($file, '.ttf');
          // Create a friendly name from filename
          $friendlyName = str_replace(['-', '_'], ' ', $fontName);
          $friendlyName = ucwords($friendlyName);
          $availableFonts[$fontName] = [
            'path' => $file,
            'name' => $friendlyName,
          ];
        }
      }
    }

    return $availableFonts;
  }

  /**
   * Gets font file path for a font ID.
   *
   * @param string $fontId
   *   The font identifier.
   *
   * @return string
   *   The font file path or empty string if not found.
   */
  public function getFontFilePath(string $fontId): string
  {
    $availableFonts = $this->getAvailableFonts();
    return isset($availableFonts[$fontId]) ? $availableFonts[$fontId]['path'] : '';
  }

  /**
   * Gets formatted font options for form elements.
   *
   * @return array
   *   Array of font options suitable for form select elements.
   */
  public function getFontOptions(): array
  {
    $availableFonts = $this->getAvailableFonts();

    $options = [];
    // Add a default system font option
    $options['default'] = t('Default (System Font)');

    // Group fonts by family
    $groupedFonts = [];
    foreach ($availableFonts as $fontId => $fontInfo) {
      // Extract family name (first part before hyphen or similar)
      $familyName = preg_replace('/[-_].*$/', '', $fontId);
      $familyName = ucfirst($familyName);

      if (!isset($groupedFonts[$familyName])) {
        $groupedFonts[$familyName] = [];
      }

      $groupedFonts[$familyName][$fontId] = $fontInfo['name'];
    }

    // Build the options array with optgroups
    foreach ($groupedFonts as $family => $fonts) {
      $options[$family] = $fonts;
    }

    return $options;
  }
}
