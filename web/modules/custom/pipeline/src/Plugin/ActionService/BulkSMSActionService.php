<?php
namespace Drupal\pipeline\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twilio\Rest\Client;

/**
 * @ActionService(
 *   id = "send_bulk_sms",
 *   label = @Translation("Send Bulk SMS Action")
 * )
 */
class BulkSMSActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface {

  protected $entityTypeManager;
  protected $loggerFactory;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration) {
    $form['account_sid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twilio Account SID'),
      '#default_value' => $configuration['account_sid'] ?? '',
      '#required' => TRUE,
    ];

    $form['auth_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twilio Auth Token'),
      '#default_value' => $configuration['auth_token'] ?? '',
      '#required' => TRUE,
    ];

    $form['from_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From Number'),
      '#default_value' => $configuration['from_number'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Your Twilio phone number (with country code, e.g., +1234567890).'),
    ];

    $form['max_batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Batch Size'),
      '#default_value' => $configuration['max_batch_size'] ?? 10,
      '#min' => 1,
      '#max' => 50,
      '#description' => $this->t('Maximum number of SMS to send in one batch (1-50).'),
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    return [
      'account_sid' => $form_state->getValue('account_sid'),
      'auth_token' => $form_state->getValue('auth_token'),
      'from_number' => $form_state->getValue('from_number'),
      'max_batch_size' => $form_state->getValue('max_batch_size'),
    ];
  }

  public function executeAction(array $config, array &$context): string {
    try {
      // Find the bulk SMS content in the context
      $sms_content = null;
      foreach ($context['results'] as $step) {
        if ($step['output_type'] === 'bulk_sms_content') {
          $sms_content = $step['data'];
          break;
        }
      }

      if (!$sms_content) {
        throw new \Exception("Bulk SMS content not found in the context.");
      }

      // Clean and decode JSON content
      $content = trim(preg_replace('/^```json\s*|\s*```$/s', '', $sms_content));
      $data = json_decode($content, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Invalid JSON format: " . json_last_error_msg());
      }

      if (!isset($data['recipients']) || !is_array($data['recipients'])) {
        throw new \Exception("JSON must contain 'recipients' array");
      }

      // Initialize Twilio client
      $client = new Client(
        $config['configuration']['account_sid'],
        $config['configuration']['auth_token']
      );

      $max_batch = $config['configuration']['max_batch_size'] ?? 10;
      $results = [];
      $errors = [];

      // Process recipients in batches
      $batch = array_slice($data['recipients'], 0, $max_batch);
      foreach ($batch as $recipient) {
        if (!isset($recipient['to_number']) || !isset($recipient['message'])) {
          $errors[] = "Invalid recipient data: missing to_number or message";
          continue;
        }

        try {
          $message = $client->messages->create(
            $recipient['to_number'],
            [
              'from' => $config['configuration']['from_number'],
              'body' => $recipient['message']
            ]
          );

          $results[] = [
            'to_number' => $recipient['to_number'],
            'message_sid' => $message->sid,
            'status' => $message->status,
          ];
        }
        catch (\Exception $e) {
          $errors[] = [
            'to_number' => $recipient['to_number'],
            'error' => $e->getMessage()
          ];
          $this->loggerFactory->get('pipeline')->error(
            'Error sending SMS to @number: @error',
            ['@number' => $recipient['to_number'], '@error' => $e->getMessage()]
          );
        }
      }

      return json_encode([
        'success' => count($results),
        'failed' => count($errors),
        'results' => $results,
        'errors' => $errors,
        'total_recipients' => count($data['recipients']),
        'processed_recipients' => count($batch),
      ]);

    }
    catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Bulk SMS error: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }
}
