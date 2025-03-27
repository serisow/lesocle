<?php
namespace Drupal\pipeline\Plugin\StepType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\file\FileInterface;
use Drupal\pipeline\ConfigurableStepTypeBase;
use Drupal\pipeline\Plugin\StepTypeExecutableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an image upload step type.
 *
 * @StepType(
 *   id = "upload_image_step",
 *   label = @Translation("Upload Image Step"),
 *   description = @Translation("Upload an image file to be used in the pipeline.")
 * )
 */
class UploadImageStep extends ConfigurableStepTypeBase implements StepTypeExecutableInterface
{

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return parent::create($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalDefaultConfiguration() {
    return [
      'image_file_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function additionalConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::additionalConfigurationForm($form, $form_state);

    // Ensure form doesn't use caching because of the file field
    $form_state->disableCache();
    
    // Image Upload field
    $form['image_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#description' => $this->t('Upload an image file (JPG, PNG, GIF, WebP). Maximum size: 2MB.'),
      '#default_value' => $this->configuration['image_file_id'] ? [$this->configuration['image_file_id']] : NULL,
      '#upload_location' => 'public://pipeline/uploads/images',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png gif jpg jpeg webp'],
        'FileSizeLimit' => ['fileLimit' => 2 * 1024 * 1024],
      ],
      '#required' => TRUE,
      '#process' => [
        [get_class($this), 'processManagedFile'],
      ],
    ];

    return $form;
  }

  /**
   * Process callback for managed file to completely remove default AJAX behavior.
   */
  public static function processManagedFile($element, FormStateInterface $form_state, &$complete_form) {
    // Get the standard element with all its defaults
    $element = ManagedFile::processManagedFile($element, $form_state, $complete_form);
    // Remove all AJAX related attributes from upload button
    if (isset($element['upload_button'])) {
      unset($element['upload_button']['#ajax']);
      // Add a custom class to mark this as our custom upload
      $element['upload_button']['#attributes']['class'][] = 'custom-file-upload';
      // Prevent triggering Drupal's ajax
      $element['upload_button']['#attributes']['data-disable-ajax'] = 'true';
    }
    // Remove all AJAX from remove button
    if (isset($element['remove_button'])) {
      //unset($element['remove_button']['#ajax']);
      $element['remove_button']['#attributes']['class'][] = 'custom-file-remove';
      $element['remove_button']['#attributes']['data-disable-ajax'] = 'true';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // Handle file upload
    $image_file = $form_state->getValue(['data', 'image_file']);
    if (!empty($image_file) && !empty($image_file[0])) {
      $this->configuration['image_file_id'] = $image_file[0];
      // Make file permanent
      $file = $this->entityTypeManager->getStorage('file')->load($this->configuration['image_file_id']);
      if ($file instanceof FileInterface) {
        $file->setPermanent();
        $file->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array &$context): string {
    try {
      // Validate file exists
      if (empty($this->configuration['image_file_id'])) {
        throw new \Exception('No image file has been uploaded.');
      }
      // Load the file
      $file = $this->entityTypeManager->getStorage('file')->load($this->configuration['image_file_id']);
      if (!$file instanceof FileInterface) {
        throw new \Exception('Invalid image file: File not found.');
      }

      // Create the result
      $result = [
        'file_id' => $file->id(),
        'uri' => $file->getFileUri(),
        'url' => $file->createFileUrl(FALSE),
        'filename' => $file->getFilename(),
        'mime_type' => $file->getMimeType(),
        'size' => $file->getSize(),
        'timestamp' => \Drupal::time()->getCurrentTime(),
      ];

      // Add the result to the context with the appropriate output type
      $context['results'][$this->getStepOutputKey()] = [
        'output_type' => 'featured_image',
        'data' => json_encode($result),
      ];
      return json_encode($result);
    } catch (\Exception $e) {
      throw new \Exception('Error processing uploaded image: ' . $e->getMessage());
    }
  }
}
