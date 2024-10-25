<?php

namespace Drupal\pipeline\Plugin\ActionService;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a generic webhook action service.
 *
 * @ActionService(
 *   id = "generic_webhook",
 *   label = @Translation("Generic Webhook")
 * )
 */
class GenericWebhookActionService extends AbstractWebhookActionService {

  /**
   * {@inheritdoc}
   */
  protected function getWebhookUrlDescription(): string {
    return $this->t('Enter the webhook endpoint URL where the data should be sent.');
  }

  /**
   * {@inheritdoc}
   */
  protected function addServiceSpecificConfiguration(array $form, array $configuration): array {
    $form['custom_headers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional Headers'),
      '#default_value' => $configuration['custom_headers'] ?? '',
      '#description' => $this->t('Enter additional headers in JSON format. Example: {"X-Custom-Header": "value"}'),
    ];

    $form['authentication'] = [
      '#type' => 'select',
      '#title' => $this->t('Authentication Type'),
      '#options' => [
        'none' => $this->t('None'),
        'basic' => $this->t('Basic Auth'),
        'bearer' => $this->t('Bearer Token'),
        'custom' => $this->t('Custom Header'),
      ],
      '#default_value' => $configuration['authentication'] ?? 'none',
      '#ajax' => [
        'callback' => '::updateAuthenticationForm',
        'wrapper' => 'auth-settings-wrapper',
      ],
    ];

    $form['auth_settings'] = [
      '#type' => 'container',
      '#prefix' => '<div id="auth-settings-wrapper">',
      '#suffix' => '</div>',
    ];

    // Add authentication fields based on configuration
    $this->addAuthenticationFields($form, $configuration['authentication'] ?? 'none', $configuration);

    return $form;
  }

  /**
   * Adds authentication fields based on selected type.
   *
   * @param array $form
   *   The form array to modify.
   * @param string $auth_type
   *   The authentication type.
   * @param array $configuration
   *   The current configuration.
   */
  protected function addAuthenticationFields(array &$form, string $auth_type, array $configuration): void {
    switch ($auth_type) {
      case 'basic':
        $form['auth_settings']['username'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Username'),
          '#default_value' => $configuration['username'] ?? '',
        ];
        $form['auth_settings']['password'] = [
          '#type' => 'password',
          '#title' => $this->t('Password'),
        ];
        break;

      case 'bearer':
        $form['auth_settings']['token'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Bearer Token'),
          '#default_value' => $configuration['token'] ?? '',
        ];
        break;

      case 'custom':
        $form['auth_settings']['header_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Header Name'),
          '#default_value' => $configuration['header_name'] ?? '',
        ];
        $form['auth_settings']['header_value'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Header Value'),
          '#default_value' => $configuration['header_value'] ?? '',
        ];
        break;
    }
  }

  /**
   * Ajax callback for authentication type selection.
   */
  public function updateAuthenticationForm(array &$form, FormStateInterface $form_state): array {
    return $form['auth_settings'];
  }

  /**
   * {@inheritdoc}
   */
  protected function addServiceSpecificConfigurationSubmit(array $config, FormStateInterface $form_state): array {
    $config['custom_headers'] = $form_state->getValue('custom_headers');
    $config['authentication'] = $form_state->getValue('authentication');

    // Add authentication settings based on type
    switch ($config['authentication']) {
      case 'basic':
        $config['username'] = $form_state->getValue(['auth_settings', 'username']);
        $config['password'] = $form_state->getValue(['auth_settings', 'password']);
        break;

      case 'bearer':
        $config['token'] = $form_state->getValue(['auth_settings', 'token']);
        break;

      case 'custom':
        $config['header_name'] = $form_state->getValue(['auth_settings', 'header_name']);
        $config['header_value'] = $form_state->getValue(['auth_settings', 'header_value']);
        break;
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateConfiguration(array $config): void {
    if (!isset($config['configuration']['webhook_url']) || empty($config['configuration']['webhook_url'])) {
      throw new \InvalidArgumentException('Webhook URL is required.');
    }

    if (!filter_var($config['configuration']['webhook_url'], FILTER_VALIDATE_URL)) {
      throw new \InvalidArgumentException('Invalid webhook URL format.');
    }

    // Validate custom headers JSON if provided
    if (!empty($config['configuration']['custom_headers'])) {
      $headers = json_decode($config['configuration']['custom_headers'], TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \InvalidArgumentException('Invalid JSON format in custom headers.');
      }
    }

    // Validate authentication settings
    switch ($config['configuration']['authentication'] ?? 'none') {
      case 'basic':
        if (empty($config['configuration']['username']) || empty($config['configuration']['password'])) {
          throw new \InvalidArgumentException('Username and password are required for Basic authentication.');
        }
        break;

      case 'bearer':
        if (empty($config['configuration']['token'])) {
          throw new \InvalidArgumentException('Bearer token is required.');
        }
        break;

      case 'custom':
        if (empty($config['configuration']['header_name']) || empty($config['configuration']['header_value'])) {
          throw new \InvalidArgumentException('Header name and value are required for custom authentication.');
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function preparePayload(array $context): array {
    // For generic webhook, we'll send everything in the context
    return [
      'timestamp' => time(),
      'data' => $context['results'] ?? [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getHeaders(array $config): array {
    $headers = ['Content-Type' => 'application/json'];

    // Add authentication headers
    switch ($config['authentication'] ?? 'none') {
      case 'basic':
        $auth = base64_encode($config['username'] . ':' . $config['password']);
        $headers['Authorization'] = 'Basic ' . $auth;
        break;

      case 'bearer':
        $headers['Authorization'] = 'Bearer ' . $config['token'];
        break;

      case 'custom':
        $headers[$config['header_name']] = $config['header_value'];
        break;
    }

    // Add custom headers if provided
    if (!empty($config['custom_headers'])) {
      $custom_headers = json_decode($config['custom_headers'], TRUE);
      if (is_array($custom_headers)) {
        $headers = array_merge($headers, $custom_headers);
      }
    }

    return $headers;
  }
}
