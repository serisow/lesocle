<?php

/**
 * Provides base implementation for webhook-based action services.
 *
 * This abstract class implements core webhook functionality including retry logic,
 * error handling, and standardized HTTP communication. It serves as the foundation
 * for all webhook-based integrations in the pipeline system.
 *
 * Core features:
 * - Implements retry logic with exponential backoff
 * - Provides standardized HTTP request handling
 * - Manages timeouts and connection issues
 * - Handles authentication and headers
 *
 * Configuration management:
 * - Webhook URL and method
 * - Authentication settings
 * - Timeout and retry settings
 * - Custom headers and parameters
 *
 * Error handling:
 * - Implements robust retry logic
 * - Provides detailed error logging
 * - Handles various HTTP response codes
 * - Manages timeout scenarios
 *
 * Important note:
 * Child classes must implement specific abstract methods for:
 * - Payload preparation
 * - Header configuration
 * - Service-specific validation
 * - Configuration form elements
 *
 * @see \Drupal\pipeline\Plugin\ActionService\GenericWebhookActionService
 * @see \Drupal\pipeline\Plugin\ActionServiceInterface
 */

namespace Drupal\pipeline\Plugin\ActionService;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for webhook action services.
 */
abstract class AbstractWebhookActionService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface
{

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new WebhookActionService.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    array                         $configuration,
                                  $plugin_id,
                                  $plugin_definition,
    ClientInterface               $http_client,
    LoggerChannelFactoryInterface $logger_factory
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
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
      $container->get('http_client'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $configuration)
  {
    $form['webhook_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Webhook URL'),
      '#default_value' => $configuration['webhook_url'] ?? '',
      '#description' => $this->getWebhookUrlDescription(),
      '#required' => TRUE,
    ];

    $form['http_method'] = [
      '#type' => 'select',
      '#title' => $this->t('HTTP Method'),
      '#options' => $this->getAvailableHttpMethods(),
      '#default_value' => $configuration['http_method'] ?? 'POST',
      '#required' => TRUE,
    ];

    $form['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout (seconds)'),
      '#min' => 1,
      '#max' => 300,
      '#default_value' => $configuration['timeout'] ?? 30,
      '#required' => TRUE,
    ];

    $form['retry_attempts'] = [
      '#type' => 'number',
      '#title' => $this->t('Retry Attempts'),
      '#min' => 0,
      '#max' => 5,
      '#default_value' => $configuration['retry_attempts'] ?? 3,
      '#required' => TRUE,
    ];

    // Allow service-specific configuration to be added
    return $this->addServiceSpecificConfiguration($form, $form_state, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    $this->configuration['webhook_url'] = $form_state->getValue('webhook_url');
    $this->configuration['http_method'] = $form_state->getValue('http_method');
    $this->configuration['timeout'] = $form_state->getValue('timeout');
    $this->configuration['retry_attempts'] = $form_state->getValue('retry_attempts');

    // Add service-specific configuration
    return $this->addServiceSpecificConfigurationSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string
  {
    try {
      // Validate configuration
      $this->validateConfiguration($config);

      // Prepare payload
      $payload = $this->preparePayload($context);

      // Send webhook
      $result = $this->sendWebhook(
        $config['configuration'],
        $payload,
        $this->getHeaders($config['configuration'])
      );

      return json_encode([
        'success' => TRUE,
        'service' => $this->getPluginId(),
        'payload' => $payload,
        'response' => $result,
      ]);
    } catch (\Exception $e) {
      $this->loggerFactory->get('pipeline')->error(
        'Webhook error for service @service: @error',
        [
          '@service' => $this->getPluginId(),
          '@error' => $e->getMessage(),
        ]
      );
      throw $e;
    }
  }

  /**
   * Sends the webhook request with retry logic.
   */
  protected function sendWebhook(array $config, array $payload, array $headers = []): string
  {
    $maxRetries = $config['retry_attempts'] ?? 3;
    $attempt = 0;
    $lastError = NULL;

    while ($attempt <= $maxRetries) {
      try {
        $response = $this->httpClient->request(
          $config['http_method'],
          $config['webhook_url'],
          [
            'headers' => $headers,
            'json' => $payload,
            'timeout' => $config['timeout'] ?? 30,
          ]
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
          return (string)$response->getBody();
        }

        throw new \Exception("HTTP Error: Status code {$statusCode}");
      } catch (RequestException $e) {
        $lastError = $e;
        $attempt++;

        if ($attempt <= $maxRetries) {
          // Exponential backoff
          sleep(pow(2, $attempt));
        }
      }
    }

    throw new \Exception(
      sprintf('Webhook failed after %d attempts. Last error: %s',
        $maxRetries,
        $lastError ? $lastError->getMessage() : 'Unknown error'
      )
    );
  }

  /**
   * Returns available HTTP methods.
   */
  protected function getAvailableHttpMethods(): array
  {
    return [
      'POST' => 'POST',
      'PUT' => 'PUT',
      'PATCH' => 'PATCH',
      'GET' => 'GET',
      'DELETE' => 'DELETE',
    ];
  }

  /**
   * Gets the webhook URL description.
   */
  abstract protected function getWebhookUrlDescription(): string;

  /**
   * Adds service-specific configuration to the form.
   */
  abstract protected function addServiceSpecificConfiguration(array &$form, FormStateInterface $form_state, array $configuration): array;

  /**
   * Handles service-specific configuration submit.
   */
  abstract protected function addServiceSpecificConfigurationSubmit(array &$form, FormStateInterface $form_state): array;

  /**
   * Validates the service-specific configuration.
   */
  abstract protected function validateConfiguration(array $config): void;

  /**
   * Prepares the payload for the webhook.
   */
  abstract protected function preparePayload(array $context): array;

  /**
   * Gets the headers for the webhook request.
   */
  abstract protected function getHeaders(array $config): array;
}
