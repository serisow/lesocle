<?php


namespace Drupal\poll\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\poll\Entity\PollInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\poll\Service\LLMAnalysisService;
use Drupal\poll\Service\PollDataService;

/**
 * Processes Poll LLM analysis.
 *
 * @QueueWorker(
 *   id = "poll_llm_processing",
 *   title = @Translation("Poll LLM Processing"),
 *   cron = {"time" = 60}
 * )
 */
class PollLlmProcessingQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface
{

  protected $entityTypeManager;
  protected $llmAnalysisService;
  protected $pollDataService;

  public function __construct(
    array                      $configuration,
                               $plugin_id,
                               $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LLMAnalysisService         $llm_analysis_service,
    PollDataService            $poll_data_service
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->llmAnalysisService = $llm_analysis_service;
    $this->pollDataService = $poll_data_service;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('poll.llm_analysis_service'),
      $container->get('poll.poll_data_service')
    );
  }

  public function processItem($data)
  {
    $poll = $this->entityTypeManager->getStorage('poll')->load($data['poll_id']);
    if (!$poll) {
      return;
    }

    $pollData = $this->pollDataService->getPollData($poll->id());
    $analysis = $this->llmAnalysisService->analyzePolls($pollData);

    $poll->setLlmAnalysis($analysis);
    $poll->save();
  }
}
