<?php
/**
 * Service for handling pipeline execution errors and logs.
 *
 * Creates and manages log files for pipeline execution errors. Captures PHP errors
 * during step execution and formats them into detailed log entries. Used primarily
 * by PipelineBatch during step execution and PipelineExecutionController for Go
 * service results.
 *
 * Core features:
 * - Creates daily log files in private://pipeline_logs/YYYY/MM directory
 * - Captures PHP errors during step execution
 * - Formats step errors and PHP errors into readable log entries
 * - Associates log files with PipelineRun entities
 *
 * Used in:
 * - PipelineBatch::processStep() for capturing errors during step execution
 * - PipelineExecutionController::receiveExecutionResult() for Go service results
 *
 * Error capture pattern:
 * ```php
 * $error_collector = $errorHandler->startErrorCapture($step_uuid);
 * try {
 *   // Step execution
 * } finally {
 *   $errorHandler->stopErrorCapture();
 * }
 * ```
 *
 * Log file format:
 * [YYYY-MM-DD HH:mm:ss] Step Description (step_type): Error message
 * [YYYY-MM-DD HH:mm:ss] PHP Error in step Description: message in file on line X
 *
 * @see \Drupal\pipeline\PipelineBatch
 * @see \Drupal\pipeline\Controller\PipelineExecutionController
 *
 */

namespace Drupal\pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\file\FileRepository;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Service for handling pipeline execution errors and logs.
 */
class PipelineErrorHandler {
  use StringTranslationTrait;

  /**
   * The entity_type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    TranslationInterface $string_translation,
    EntityTypeManagerInterface $entity_type_manager

  ) {
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->dateFormatter = $date_formatter;
    $this->setStringTranslation($string_translation);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Creates a log file for a pipeline run.
   */
  /**
   * Creates a log file for a pipeline run.
   */
  public function createLogFile(array $step_results, $pipeline_run_id): ?FileInterface {
    try {
      $logs = [];
      // Create year/month directory structure
      $directory = 'private://pipeline_logs/' . date('Y') . '/' . date('m');
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      // Change filename to be date-based instead of pipeline-run based
      $filename = sprintf('pipeline_%s.log', date('Y-m-d'));
      $uri = $directory . '/' . $filename;

      foreach ($step_results as $step_uuid => $step) {
        // Add pipeline run ID to log entries for traceability
        if (!empty($step['error_message'])) {
          $logs[] = sprintf(
            "[%s] Pipeline Run %d - Step %s (%s): %s",
            $this->dateFormatter->format(time(), 'custom', 'Y-m-d H:i:s'),
            $pipeline_run_id,
            $step['step_description'],
            $step['step_type'],
            $step['error_message']
          );
        }
      }

      if (!empty($logs)) {
        $log_content = implode("\n", $logs) . "\n";  // Add newline after entries

        // Check if file exists
        if (file_exists($uri)) {
          // Append to existing file
          file_put_contents($uri, $log_content, FILE_APPEND);
          // Get existing file entity
          $files = $this->entityTypeManager
            ->getStorage('file')
            ->loadByProperties(['uri' => $uri]);
          $file = reset($files);
        } else {
          // Create new file
          $file = $this->fileRepository->writeData(
            $log_content,
            $uri,
            FileExists::Replace
          );
        }

        if ($file) {
          $file->setPermanent();
          $file->save();
          return $file;
        }
      }

      return NULL;
    } catch (\Exception $e) {
      \Drupal::logger('pipeline')->error('Failed to create log file: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
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
