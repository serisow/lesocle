<?php
namespace Drupal\pipeline\Service;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileRepository;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Service for handling pipeline execution errors and logs.
 */
class PipelineErrorHandler {
  use StringTranslationTrait;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepository
   */
  protected $fileRepository;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Stores errors for all steps during execution.
   *
   * @var array
   */
  protected $errorCollector = [];

  /**
   * Constructs a new PipelineErrorHandler.
   */
  public function __construct(
    FileSystemInterface $file_system,
    FileRepository $file_repository,
    DateFormatter $date_formatter,
    TranslationInterface $string_translation
  ) {
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->dateFormatter = $date_formatter;
    $this->setStringTranslation($string_translation);
  }

  /**
   * Creates a log file for a pipeline run.
   */
  public function createLogFile(array $step_results, $pipeline_run_id) {
    $logs = [];
    $error_collector = $this->errorCollector;

    foreach ($step_results as $step_uuid => $step) {
      // Capture step errors
      if (!empty($step['error_message'])) {
        $logs[] = sprintf(
          "[%s] Step %s (%s): %s",
          $this->dateFormatter->format($step['start_time'], 'custom', 'Y-m-d H:i:s'),
          $step['step_description'],
          $step['step_type'],
          $step['error_message']
        );
      }

      // Add collected PHP errors for this step if any
      if (isset($error_collector[$step_uuid])) {
        foreach ($error_collector[$step_uuid] as $error) {
          $logs[] = sprintf(
            "[%s] PHP %s in step %s: %s in %s on line %d",
            $this->dateFormatter->format($error['time'], 'custom', 'Y-m-d H:i:s'),
            $error['severity'],
            $step['step_description'],
            $error['message'],
            $error['file'],
            $error['line']
          );
        }
      }
    }

    if (!empty($logs)) {
      // Create year/month directory structure
      $directory = 'private://pipeline_logs/' . date('Y') . '/' . date('m');
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      $filename = sprintf('pipeline_run_%d_%s.log', $pipeline_run_id, date('Y-m-d_His'));
      $uri = $directory . '/' . $filename;

      $file_contents = implode("\n", $logs);

      try {
        $file = $this->fileRepository->writeData(
          $file_contents,
          $uri,
          FileExists::Replace
        );

        if ($file) {
          $file->setPermanent();
          $file->save();
          return $file;
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('pipeline')->error('Failed to create log file: @message', ['@message' => $e->getMessage()]);
      }
    }

    return NULL;
  }

  /**
   * Starts error capture for a specific step.
   *
   * @param string $step_uuid
   *   The UUID of the step being executed.
   */
  public function startErrorCapture($step_uuid) {
    // Initialize empty array for this step's errors
    $this->errorCollector[$step_uuid] = [];

    // Set custom error handler that RETURNS TRUE to prevent display
    set_error_handler(function($severity, $message, $file, $line) use ($step_uuid) {
      // Store the error in the collector
      $this->errorCollector[$step_uuid][] = [
        'time' => time(),
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'severity' => $this->getSeverityLabel($severity),
      ];

      // Return TRUE to prevent the error from being displayed
      return TRUE;
    });

    return $this->errorCollector;
  }

  /**
   * Stops error capture and returns collected errors.
   */
  public function stopErrorCapture() {
    restore_error_handler();
  }

  /**
   * Gets human-readable error severity label.
   *
   * @param int $severity
   *   PHP error severity level.
   *
   * @return string
   *   Translated severity label.
   */
  protected function getSeverityLabel($severity) {
    switch ($severity) {
      case E_ERROR:
        return $this->t('Error');
      case E_WARNING:
        return $this->t('Warning');
      case E_PARSE:
        return $this->t('Parse Error');
      case E_NOTICE:
        return $this->t('Notice');
      case E_CORE_ERROR:
        return $this->t('Core Error');
      case E_CORE_WARNING:
        return $this->t('Core Warning');
      case E_COMPILE_ERROR:
        return $this->t('Compile Error');
      case E_COMPILE_WARNING:
        return $this->t('Compile Warning');
      case E_USER_ERROR:
        return $this->t('User Error');
      case E_USER_WARNING:
        return $this->t('User Warning');
      case E_USER_NOTICE:
        return $this->t('User Notice');
      case E_STRICT:
        return $this->t('Strict Notice');
      case E_RECOVERABLE_ERROR:
        return $this->t('Recoverable Error');
      case E_DEPRECATED:
        return $this->t('Deprecated');
      case E_USER_DEPRECATED:
        return $this->t('User Deprecated');
      default:
        return $this->t('Unknown Error (@severity)', ['@severity' => $severity]);
    }
  }
}
