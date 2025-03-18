<?php
namespace Drupal\pipeline_integration\Plugin\ActionService;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ActionService(
 *   id = "generate_pdf",
 *   label = @Translation("Generate PDF Action")
 * )
 */
class GeneratePdfActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface
{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The file repository service.
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
   * Constructs a GeneratePdfActionService object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   * The file system service.
   */
  public function __construct(
    array $configuration, $plugin_id, $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FileRepositoryInterface $file_repository,
    FileSystemInterface $file_system
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->fileRepository = $file_repository;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('file.repository'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string
  {
    // Retrieve the previous step's result.
    $result = $context['last_response'] ?? '';
    //@TODO SSOW: VIA THE PROMPT ASK FOR structure with:
    // - appropriate name which make sens relativr to the content
    // - Well structured html
    // { filename: "my-nice-doc", content: "<html>...</html>" }

    if (empty($result)) {
      throw new \Exception("No input data found for PDF generation.");
    }

    // Remove ```json prefix and ``` suffix if present
    $result = preg_replace('/^```json\s*|\s*```$/s', '', $result);

    // Trim any whitespace
    $result = trim($result);

    // Decode the JSON content
    $data = json_decode($result, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON format: " . json_last_error_msg());
    }

    if (!isset($data['filename']) || !isset($data['content'])) {
      throw new \Exception("JSON must contain 'filename' and 'content' fields");
    }

    // Initialize Dompdf with options.
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);

    // Load HTML content.
    $dompdf->loadHtml($data['content']);

    // @TODO SSOW: Use the $config object
    // (Optional) Set paper size and orientation.
    $dompdf->setPaper($config['configuration']['page_size'], $config['configuration']['orientation']);

    // Render the HTML as PDF.
    $dompdf->render();

    // Output the generated PDF to a string.
    $pdf_output = $dompdf->output();

    // Save the PDF to Drupal's file system.
    $file = $this->savePdfFile($pdf_output, $data['filename']);

    if (!$file) {
      throw new \Exception("Failed to save the generated PDF.");
    }

    // Optionally, create a Media entity for the PDF.
    $media_id = $this->createMediaEntity($file);

    if (!$media_id) {
      throw new \Exception("Failed to create Media entity for the PDF.");
    }

    return json_encode([
      'file_id' => $file->id(),
      'media_id' => $media_id,
      'uri' => $file->getFileUri(),
    ]);
  }

  /**
   * Saves the generated PDF to Drupal's file system.
   *
   * @param string $pdf_content
   *   The binary content of the PDF.
   * @param string $filename
   *   The desired filename and path within the public files directory.
   *
   * @return FileInterface|null
   *   The File entity if saved successfully, NULL otherwise.
   */
  protected function savePdfFile(string $pdf_content, string $filename): ?FileInterface {
    try {
      $directory = 'public://generated_pdfs';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

       return $this->fileRepository->writeData(
         $pdf_content,
        "$directory/$filename",
        FileExists::Replace
      );
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error saving PDF file: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Creates a Media entity for the generated PDF.
   *
   * @param FileInterface $file
   *   The File entity representing the PDF.
   *
   * @return int|null
   *   The Media entity ID if created successfully, NULL otherwise.
   */
  protected function createMediaEntity(FileInterface $file): ?int
  {
    try {
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => 'document', // Ensure you have a 'document' media type configured.
        'name' => 'Generated PDF ' . $file->getFilename(),
        'field_media_document' => [
          'target_id' => $file->id(),
          'display' => 1,
        ],
      ]);
      $media->save();
      return $media->id();
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error creating Media entity for PDF: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, array $configuration = [])
  {
    // Define any additional configuration form fields here.
    // For PDF generation, you might allow setting page size, orientation, etc.

    $form['page_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Page Size'),
      '#options' => [
        'A4' => $this->t('A4'),
        'Letter' => $this->t('Letter'),
        'Legal' => $this->t('Legal'),
        // Add more sizes as needed.
      ],
      '#default_value' => $configuration['page_size'] ?? 'A4',
      '#description' => $this->t('Select the page size for the generated PDF.'),
    ];

    $form['orientation'] = [
      '#type' => 'select',
      '#title' => $this->t('Orientation'),
      '#options' => [
        'portrait' => $this->t('Portrait'),
        'landscape' => $this->t('Landscape'),
      ],
      '#default_value' => $configuration['orientation'] ?? 'portrait',
      '#description' => $this->t('Select the orientation for the generated PDF.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state)
  {
    return [
      'page_size' => $form_state->getValue('page_size'),
      'orientation' => $form_state->getValue('orientation'),
    ];
  }

}
