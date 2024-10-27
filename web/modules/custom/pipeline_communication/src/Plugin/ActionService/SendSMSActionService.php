<?php
namespace Drupal\pipeline_communication\Plugin\ActionService;

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
 *   id = "send_sms",
 *   label = @Translation("Send SMS Action")
 * )
 */
class SendSMSActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface
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
   * Constructs a SendSMSActionService object.
   */
  public function __construct(
    array                         $configuration,
                                  $plugin_id,
                                  $plugin_definition,
    EntityTypeManagerInterface    $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
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
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration)
  {
    $form['account_sid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twilio Account SID'),
      '#default_value' => $configuration['account_sid'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twilio Account SID.'),
    ];

    $form['auth_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twilio Auth Token'),
      '#default_value' => $configuration['auth_token'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twilio Auth Token.'),
    ];

    $form['from_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From Number'),
      '#default_value' => $configuration['from_number'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter your Twilio phone number (with country code, e.g., +1234567890).'),
    ];

    $form['to_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To Number'),
      '#default_value' => $configuration['to_number'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Enter the recipient phone number (with country code, e.g., +1234567890).'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    return [
      'account_sid' => $form_state->getValue('account_sid'),
      'auth_token' => $form_state->getValue('auth_token'),
      'from_number' => $form_state->getValue('from_number'),
      'to_number' => $form_state->getValue('to_number'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string
  {
    try {
      // Find the SMS content in the context
      $sms_content = null;
      foreach ($context['results'] as $step) {
        if ($step['output_type'] === 'sms_content') {
          $sms_content = $step['data'];
          break;
        }
      }

      if (!$sms_content) {
        throw new \Exception("SMS content not found in the context.");
      }

      // Remove ```json prefix and ``` suffix if present
      $content = preg_replace('/^```json\s*|\s*```$/s', '', $sms_content);

      // Trim any whitespace
      $content = trim($content);

      // Decode the JSON content
      $data = json_decode($content, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Invalid JSON format: " . json_last_error_msg());
      }

      if (!isset($data['message'])) {
        throw new \Exception("JSON must contain 'message' field");
      }

      // Initialize Twilio client
      $client = new Client(
        $config['configuration']['account_sid'],
        $config['configuration']['auth_token']
      );

      // Send SMS
      $message = $client->messages->create(
        $config['configuration']['to_number'],
        [
          'from' => $config['configuration']['from_number'],
          'body' => $data['message']
        ]
      );

      return json_encode([
        'message_sid' => $message->sid,
        'status' => $message->status,
        'message' => $data['message'],
      ]);

    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error('Error sending SMS: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }
}
