<?php

namespace Drupal\poll\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\poll\Service\PollDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class PollAnalysisController extends ControllerBase {
  protected $pollDataService;

  public function __construct(PollDataService $poll_data_service) {
    $this->pollDataService = $poll_data_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('poll.poll_data_service')
    );
  }
  public function analyzePolls($poll_id) {
    try {
      $poll = $this->entityTypeManager()->getStorage('poll')->load($poll_id);
      if (!$poll) {
        throw new \Exception('Poll not found');
      }

      $analysis = $poll->getLlmAnalysis();
      $shortAnswers = $this->getShortAnswers($poll);

      if (empty($analysis)) {
        return new JsonResponse(['error' => 'LLM analysis not yet available'], 404);
      }

      $response = [
        'poll_name' => $poll->label(),
        'participant_count' => count($poll->getParticipants()),
        'question_count' => $poll->getQuestionCount(),
        'created_date' => $poll->getCreatedTime(),
        'status' => $poll->getStatus(),
        'analysis' => $analysis,
        'short_answers' => $shortAnswers

      ];

      return new JsonResponse($response);
    } catch (\Exception $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }
  }
  private function getShortAnswers($poll) {
    $shortAnswers = [];
    foreach ($poll->getQuestionTypes() as $question) {
      if ($question->getPluginId() == 'short_answer') {
        $responses = [];
        foreach ($poll->getParticipants() as $participant) {
          $pollResponses = $participant->get('poll_responses')->first()->getValue();
          if (isset($pollResponses[$question->getUuid()])) {
            $responses[] = $pollResponses[$question->getUuid()]['answer'];
          }
        }
        $shortAnswers[] = [
          'question' => $question->getConfiguration()['data']['question_text'],
          'responses' => $responses
        ];
      }
    }
    return $shortAnswers;
  }
}
