<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\file\FileRepositoryInterface;

/**
 * Controller for handling file uploads in pipeline step forms.
 */
class PipelineFileUploadController extends ControllerBase
{

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new PipelineFileUploadController.
   *
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(FileRepositoryInterface $file_repository, FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager)
  {
    $this->fileRepository = $file_repository;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('file.repository'),
      $container->get('file_system'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Handles file uploads for pipeline step forms.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response with the uploaded file information.
   */
  public function handleFileUpload(Request $request) {
    // Get the uploaded file
    $files = $request->files->get('files');
    if (!$files) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'No files were uploaded.',
      ]);
    }

    // Determine the field name and pipeline information
    $fieldName = $request->get('field_name');
    $pipelineId = $request->get('pipeline_id');
    $stepType = $request->get('step_type');
    $uuid = $request->get('uuid');

    if (!$fieldName || !$pipelineId) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Missing required parameters.',
      ]);
    }

    // Determine upload directory based on field name
    $uploadDirectory = 'public://pipeline/uploads/';
    if (strpos($fieldName, 'image_file') !== FALSE) {
      $uploadDirectory .= 'images';
      $extensions = 'png gif jpg jpeg webp';
      $configKey = 'image_file_id';
    } elseif (strpos($fieldName, 'audio_file') !== FALSE) {
      $uploadDirectory .= 'audio';
      $extensions = 'mp3 wav ogg';
      $configKey = 'audio_file_id';
    } else {
      $uploadDirectory .= 'files';
      $extensions = 'jpg jpeg png gif pdf doc docx xls xlsx mp3 wav ogg mp4';
      $configKey = 'file_id';
    }

    // Ensure the upload directory exists
    $this->fileSystem->prepareDirectory($uploadDirectory, FileSystemInterface::CREATE_DIRECTORY);

    // Get the uploaded file from the request
    $uploadedFile = reset($files);
    if (!$uploadedFile) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Error accessing the uploaded file.',
      ]);
    }

    try {
      // Validate file extension
      $filename = $uploadedFile->getClientOriginalName();
      $ext = pathinfo($filename, PATHINFO_EXTENSION);
      if (!in_array(strtolower($ext), explode(' ', $extensions))) {
        return new JsonResponse([
          'status' => 'error',
          'message' => "File extension '$ext' is not allowed. Allowed extensions: $extensions",
        ]);
      }

      // Save the file
      $destination = $uploadDirectory . '/' . $filename;
      $file = $this->fileRepository->writeData(
        file_get_contents($uploadedFile->getRealPath()),
        $destination,
        FileExists::Rename
      );

      // Make the file permanent
      $file->setPermanent();
      $file->save();

      // Format the file size in a human-readable way
      $filesize_bytes = $file->getSize();
      $filesize = $this->formatFileSize($filesize_bytes);

      // Save the file ID to the pipeline configuration
      if ($pipelineId && $uuid) {
        $pipeline = $this->entityTypeManager->getStorage('pipeline')->load($pipelineId);
        if ($pipeline) {
          $step_type = $pipeline->getStepType($uuid);
          if ($step_type) {
            $configuration = $step_type->getConfiguration();
            $configuration['data'][$configKey] = $file->id();
            $step_type->setConfiguration($configuration);
            $pipeline->save();

            // Log successful config update
            $this->getLogger('pipeline')->notice('Updated file ID (@fid) for @field_name in pipeline @pipeline, step @uuid', [
              '@fid' => $file->id(),
              '@field_name' => $fieldName,
              '@pipeline' => $pipelineId,
              '@uuid' => $uuid,
            ]);
          }
        }
      }

      // Return file information
      return new JsonResponse([
        'status' => 'success',
        'fid' => $file->id(),
        'filename' => $file->getFilename(),
        'filesize' => $filesize,
        'mime' => $file->getMimeType(),
        'url' => $file->createFileUrl(FALSE),
        'field_name' => $fieldName,
        'config_updated' => isset($pipeline),
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('pipeline')->error('File upload error: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Error saving the file: ' . $e->getMessage(),
      ]);
    }
  }

  /**
   * Formats a file size into a human-readable string.
   *
   * @param int $size
   *   The file size in bytes.
   *
   * @return string
   *   The formatted file size.
   */
  protected function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
      $size /= 1024;
      $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
  }
}
