<?php
/**
 * Provides base implementation for Step Type plugins.
 *
 * Step Types are the fundamental building blocks of pipeline execution, defining
 * individual operations that can be chained together. This base class implements
 * core plugin functionality and state management for all step types.
 *
 * Core functionalities:
 * - Plugin configuration management
 * - Step execution state tracking
 * - Weight-based ordering
 * - Output key management
 * - Step description handling
 *
 * Important behaviors:
 * - Maintains unique UUID within pipeline context
 * - Handles step weights for execution ordering
 * - Manages step output for subsequent steps
 * - Provides AJAX integration support
 * - Implements dependency calculation
 *
 * Configuration management:
 * - Handles default configuration
 * - Manages step-specific settings
 * - Processes plugin dependencies
 * - Supports configuration inheritance
 *
 * Key relationships:
 * - Works with Pipeline entity for execution context
 * - Integrates with StepTypeManager for plugin handling
 * - Supports ConfigurableStepTypeInterface extensions
 * - Uses EntityTypeManager for entity operations
 *
 * Integration points:
 * - Plugin system for step type registration
 * - Form API for configuration
 * - Entity system for persistence
 * - AJAX system for interactive updates
 *
 * @see \Drupal\pipeline\Plugin\StepType\Annotation\StepType
 * @see \Drupal\pipeline\ConfigurableStepTypeInterface
 * @see \Drupal\pipeline\Plugin\StepTypeInterface
 * @see \Drupal\pipeline\Plugin\StepTypeManager
 */
namespace Drupal\pipeline;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\pipeline\Plugin\StepTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a base class for step type.
 *
 * @see \Drupal\pipeline\Plugin\StepType\Annotation\StepType
 * @see \Drupal\pipeline\Plugin\StepTypeInterface
 * @see \Drupal\pipeline\ConfigurableStepTypeInterface
 * @see \Drupal\pipeline\Plugin\StepTypeInterface
 * @see \Drupal\pipeline\StepTypeBase
 * @see \Drupal\pipeline\Plugin\StepTypeManager
 * @see plugin_api
 */
abstract class StepTypeBase extends PluginBase implements StepTypeInterface, ContainerFactoryPluginInterface {

  /**
   * The step type ID.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The weight of the step type.
   *
   * @var int|string
   */
  protected $weight = '';

  protected $step_description;

  protected $response = '';


  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The request stack
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;


  /** @var \Drupal\Core\Entity\EntityTypeManager */
  protected $entityTypeManager;

  /** @var \Drupal\Core\Form\FormBuilderInterface */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id, $plugin_definition,
    LoggerInterface $logger,
    RequestStack $request_stack,
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->logger = $logger;
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('pipeline'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#markup' => '',
      '#step_type' => [
        'id' => $this->pluginDefinition['id'],
        'label' => $this->label(),
        'description' => $this->pluginDefinition['description'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getStepDescription() {
    return $this->configuration['step_description'];
  }

  /**
   * {@inheritdoc}
   */
  public function setStepDescription($description) {
    $this->step_description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(): string {
    return $this->configuration['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function setResponse(string $response) {
    $this->response = $response;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStepOutputKey() : string {
    return $this->configuration['step_output_key'] ?? '';
  }

  public function getStepOutputType() : string {
    return $this->configuration['output_type'] ?? '';
  }

  /**
   * Need to be override in LLMStep type.
   * {@inheritdoc}
   */
  public function getPrompt() : string {
    return  '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [
      'uuid' => $this->getUuid(),
      'id' => $this->getPluginId(),
      'weight' => $this->getWeight(),
      'data' => $this->configuration,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration += [
      'data' => [],
      'uuid' => '',
      'weight' => '',
    ];
    $this->configuration = $configuration['data'] + $this->defaultConfiguration();
    $this->uuid = $configuration['uuid'];
    $this->weight = $configuration['weight'];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'step_description' => '',
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  public function isAjax(): bool {
    $request = $this->requestStack->getCurrentRequest();
    return $request->isXmlHttpRequest();
  }

}
