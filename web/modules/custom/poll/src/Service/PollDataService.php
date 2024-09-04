<?php


namespace Drupal\poll\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class PollDataService
{
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager)
  {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function getPollData($poll_id)
  {
    $poll = $this->entityTypeManager->getStorage('poll')->load($poll_id);
    if (!$poll) {
      throw new \Exception('Poll not found');
    }

    $participants = $this->entityTypeManager->getStorage('participant')
      ->loadByProperties(['poll' => $poll_id]);

    return $this->formatPollResponses($poll, $participants);
  }

  private function formatPollResponses($poll, $participants)
  {
    $questions = $poll->getQuestionTypes();
    $formatted_questions = [];
    $formatted_participants = [];

    foreach ($questions as $question) {
      $formatted_question = [
        'id' => $question->getUuid(),
        'type' => $question->getPluginId(),
        'text' => $question->getConfiguration()['data']['question_text'],
        'summary' => $this->initializeSummary($question)
      ];

      if ($question->getPluginId() == 'multiple_choice') {
        $formatted_question['options'] = array_column($question->getConfiguration()['data']['options'], 'text');
      }

      $formatted_questions[$question->getUuid()] = $formatted_question;
    }

    foreach ($participants as $participant) {
      $formatted_participant = [
        'id' => $participant->id(),
        'responses' => []
      ];

      $responses = $participant->get('poll_responses')->first()->getValue();
      foreach ($responses as $response) {
        $question_id = $response['question_uuid'];
        $answer = $this->formatAnswer($response);
        $formatted_participant['responses'][] = [
          'question_id' => $question_id,
          'answer' => $answer
        ];
        $this->updateSummary($formatted_questions[$question_id]['summary'], $answer);
      }

      $formatted_participants[] = $formatted_participant;
    }

    return [
      'poll_id' => $poll->id(),
      'poll_title' => $poll->label(),
      'total_participants' => count($participants),
      'questions' => array_values($formatted_questions),
      'participants' => $formatted_participants
    ];
  }

  private function initializeSummary($question)
  {
    switch ($question->getPluginId()) {
      case 'multiple_choice':
        return array_fill_keys(array_column($question->getConfiguration()['data']['options'], 'text'), 0);
      case 'true_false':
        return ['true' => 0, 'false' => 0];
      case 'short_answer':
        return ['response_count' => 0, 'unique_responses' => 0];
      default:
        return [];
    }
  }

  private function updateSummary(&$summary, $answer)
  {
    if (is_array($summary)) {
      if (isset($summary['response_count'])) {
        $summary['response_count']++;
        $summary['unique_responses'] = count(array_unique(array_column($summary, 'answer')));
      } else {
        foreach ((array)$answer as $option) {
          if (isset($summary[$option])) {
            $summary[$option]++;
          }
        }
      }
    }
  }

  private function formatAnswer($response)
  {
    $answer = $response['answer'];
    if ($response['question_type'] == 'multiple_choice') {
      $answer = is_array($answer) ? $answer : [$answer];
      $answer = array_map(function ($option) use ($response) {
        return $response['multiple_choice_options'][$option]['text'];
      }, $answer);
    } elseif ($response['question_type'] == 'true_false') {
      $answer = $answer == '1';
    }
    return $answer;
  }
}
