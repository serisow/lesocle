<?php
namespace Drupal\pipeline_run\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'duration' formatter.
 *
 * @FieldFormatter(
 *   id = "pipeline_run_duration",
 *   label = @Translation("Pipeline Run Duration"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class DurationFormatter extends FormatterBase
{

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $elements = [];

    foreach ($items as $delta => $item) {
      $duration = $item->value;
      if ($duration < 60) {
        $formatted = $this->t('@duration sec', ['@duration' => $duration]);
      } else {
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        $formatted = $this->t('@minutes min @seconds sec', [
          '@minutes' => $minutes,
          '@seconds' => $seconds,
        ]);
      }

      $elements[$delta] = ['#markup' => $formatted];
    }

    return $elements;
  }

}
