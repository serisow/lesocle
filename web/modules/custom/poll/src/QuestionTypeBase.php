<?php

namespace Drupal\poll;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\poll\Plugin\QuestionTypeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a base class for question type.
 *
 * @see \Drupal\poll\Plugin\QuestionType\Annotation\QuestionType
 * @see \Drupal\poll\Plugin\QuestionTypeInterface
 * @see \Drupal\poll\ConfigurableQuestionTypeInterface
 * @see \Drupal\poll\Plugin\QuestionTypeInterface
 * @see \Drupal\poll\QuestionTypeBase
 * @see \Drupal\poll\Plugin\QuestionTypeManager
 * @see plugin_api
 */
abstract class QuestionTypeBase extends PluginBase implements QuestionTypeInterface, ContainerFactoryPluginInterface {

  /**
   * The question type ID.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The weight of the question type.
   *
   * @var int|string
   */
  protected $weight = '';

  protected $questionText;

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
      $container->get('logger.factory')->get('poll'),
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
      '#question_type' => [
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
  public function getQuestionText() {
    return $this->configuration['question_text'];
  }

  /**
   * {@inheritdoc}
   */
  public function setQuestionText($text) {
    $this->questionText = $text;
    return $this;
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
        'question_text' => '',
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
