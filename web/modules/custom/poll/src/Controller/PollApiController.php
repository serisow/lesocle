<?php
namespace Drupal\poll\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\poll\Entity\PollInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class PollApiController extends ControllerBase {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new PollApiController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Retrieves poll data for a participant.
   *
   * @param string $access_token
   *   The participant's access token.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing poll data or an error message.
   */
  public function getPollForParticipant($access_token) {
    try {
      [$participant, $poll] = $this->getParticipantAndPoll($access_token);

      if ($participant->hasCompletedPoll($poll->id())) {
        return new JsonResponse(['error' => 'You have already completed this poll'], 403);
      }
      // Update participant status to 'started' if it was 'pending'
      if ($participant->getStatus() === 'pending') {
        $participant->setStatus('started');
        $participant->setLastAccess(time());
        $participant->save();
      }
      // Get poll data
      $pollData = $this->preparePollData($poll);
      $pollData['participant'] = [
        'firstName' => $participant->getFirstName(),
        'lastName' => $participant->getLastName(),
      ];
      $pollData['accessToken'] = $access_token;
      // Use a boolean flag instead of langCode
      $pollData['isFrench'] = $poll->getLangcode() === 'fr';
      return new JsonResponse($pollData);
      // Rest of the method logic
    } catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 404);
    }
  }

  /**
   * Prepares poll data for API response.
   *
   * @param \Drupal\poll\Entity\PollInterface $poll
   *   The poll entity.
   *
   * @return array
   *   An array of poll data.
   */
  private function preparePollData(PollInterface $poll) {
    $pollData = [
      'id' => $poll->id(),
      'label' => $poll->label(),
      'instructions' => $poll->getInstructions(),
      'questions' => [],
    ];
    foreach ($poll->getQuestionTypes() as $question_type) {
      $questionData = [
        'uuid' => $question_type->getUuid(),
        'type' => $question_type->getPluginId(),
        'text' => $question_type->getConfiguration()['data']['question_text'],
      ];
      if ($question_type->getPluginId() === 'multiple_choice') {
        $questionData['options'] = array_map(function($option) {
          return $option['text'];
        }, $question_type->getConfiguration()['data']['options']);
        $questionData['single_choice'] = (bool) $question_type->getConfiguration()['data']['single_choice'] ?? false;
      }
      $pollData['questions'][] = $questionData;
    }
    return $pollData;
  }

  /**
   * Handler of "Start poll" click action from the client.
   *
   * @param Request $request
   * @return JsonResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function registerPollStart(Request $request) {
    $content = json_decode($request->getContent(), true);
    $accessToken = $content['accessToken'] ?? null;
    if (!$accessToken) {
      return new JsonResponse(['error' => 'Access token is required'], 400);
    }
    try {
      [$participant, $poll] = $this->getParticipantAndPoll($accessToken);
      if ($participant->hasCompletedPoll($poll->id())) {
        return new JsonResponse(['error' => 'You have already completed this poll'], 403);
      }

      $currentTime = time();
      $participant->setStatus('pending');
      $participant->setLastAccess($currentTime);
      $participant->save();
      return new JsonResponse([
        'message' => 'Poll start registered successfully',
      ]);
    } catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 404);
    }
  }

  public function saveAnswer(Request $request) {
    $content = json_decode($request->getContent(), true);
    $accessToken = $content['accessToken'] ?? null;
    $questionUuid = $content['questionUuid'] ?? null;
    $answer = $content['answer'] ?? null;

    if (!$accessToken || !$questionUuid) {
      return new JsonResponse(['error' => 'Access token and question UUID are required'], 400);
    }

    $participants = $this->entityTypeManager->getStorage('participant')
      ->loadByProperties(['access_token' => $accessToken]);

    if (empty($participants)) {
      return new JsonResponse(['error' => 'Invalid access token'], 404);
    }

    $participant = reset($participants);
    $poll = $participant->getPoll();

    // Check if the participant has already completed this poll
    if ($participant->hasCompletedPoll($poll->id())) {
      return new JsonResponse(['error' => 'This poll has already been completed'], 403);
    }

    $this->updatePollResponses($participant, $questionUuid, $answer);

    $participant->setLastAccess(time());
    $participant->save();

    return new JsonResponse(['message' => 'Answer saved successfully']);
  }

  private function updatePollResponses($participant, $questionUuid, $answer) {
    $poll = $participant->getPoll();
    $question = $this->getQuestionByUuid($poll, $questionUuid);
    if (!$question) {
      throw new \Exception('Question not found');
    }
    $pollResponses = $participant->get('poll_responses')->first()?->getValue() ?: [];
    $responseData = [
      'question_uuid' => $questionUuid,
      'question_type' => $question->getPluginId(),
      'question_text' => $question->getQuestionText(),
      'answer' => $answer,
      'timestamp' => time(),
    ];
    // Add question-type specific data
    if ($question->getPluginId() == 'multiple_choice') {
      $responseData['multiple_choice_options'] = $question->getOptions();
    }
    $pollResponses[$questionUuid] = $responseData;
    $participant->set('poll_responses', [$pollResponses]);
  }
  public function submitPoll(Request $request) {
    $content = json_decode($request->getContent(), true);
    if (!isset($content['accessToken'])) {
      return new JsonResponse(['error' => 'Access token is required'], 400);
    }
    try {
      [$participant, $poll] = $this->getParticipantAndPoll($content['accessToken']);
      if ($participant->hasCompletedPoll($poll->id())) {
        return new JsonResponse(['error' => 'This poll has already been completed'], 403);
      }
      // Update all responses
      if (isset($content['questions']) && is_array($content['questions'])) {
        foreach ($content['questions'] as $question) {
          $questionUuid = $question['uuid'];
          $userAnswer = $question['data']['user_answer'] ?? null;
          if ($userAnswer !== null) {
            $this->updatePollResponses($participant, $questionUuid, $userAnswer);
          }
        }
      }
      $currentTime = time();
      $participant->setLastAccess($currentTime);
      $participant->setStatus('completed');
      $participant->save();

      return new JsonResponse(['message' => 'Poll submitted successfully']);
    } catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 404);
    }
  }

  /**
   * Helper method to get a question by its UUID.
   *
   * @param \Drupal\poll\Entity\PollInterface $poll
   *   The poll entity.
   * @param string $uuid
   *   The UUID of the question.
   *
   * @return \Drupal\poll\Plugin\QuestionTypeInterface|null
   *   The question plugin instance, or null if not found.
   */
  private function getQuestionByUuid($poll, $uuid) {
    foreach ($poll->getQuestionTypes() as $question) {
      if ($question->getUuid() === $uuid) {
        return $question;
      }
    }
    return null;
  }

  private function getParticipantAndPoll(string $access_token): array {
    $participants = $this->entityTypeManager->getStorage('participant')
      ->loadByProperties(['access_token' => $access_token]);
    if (empty($participants)) {
      throw new \InvalidArgumentException('Invalid access token');
    }
    $participant = reset($participants);
    $poll = $participant->getPoll();
    if (!$poll) {
      throw new \InvalidArgumentException('No poll associated with this participant');
    }
    return [$participant, $poll];
  }

  public function getPollResponses($poll_id) {
    $poll = $this->entityTypeManager->getStorage('poll')->load($poll_id);
    if (!$poll) {
      throw new NotFoundHttpException('Poll not found');
    }

    $participants = $this->entityTypeManager->getStorage('participant')
      ->loadByProperties(['poll' => $poll_id]);

    $formatted_data = $this->formatPollResponses($poll, $participants);

    return new JsonResponse($formatted_data);
  }

  private function formatPollResponses($poll, $participants) {
    $questions = $poll->getQuestionTypes();
    $formatted_questions = [];
    $formatted_participants = [];

    foreach ($questions as $question) {
      $formatted_question = [
        'id' => $question->getUuid(),
        'type' => $question->getPluginId(),
        'text' => $question->getConfiguration()['data']['question_text']
      ];

      if ($question->getPluginId() == 'multiple_choice') {
        $formatted_question['options'] = array_column($question->getConfiguration()['data']['options'], 'text');
      }

      if ($question->getPluginId() == 'likert') {
        $formatted_question['scale'] = array_column($question->getConfiguration()['data']['options'], 'text');
      }

      $formatted_questions[] = $formatted_question;
    }

    foreach ($participants as $participant) {
      $formatted_participant = [
        'id' => $participant->id(),
        'responses' => []
      ];

      $responses = $participant->get('poll_responses')->first()->getValue();
      foreach ($responses as $response) {
        $answer = $response['answer'];

        if ($response['question_type'] == 'multiple_choice') {
          $answer = is_array($answer) ? $answer : [$answer];
          $answer = array_map(function($option) use ($response) {
            return $response['multiple_choice_options'][$option]['text'];
          }, $answer);
        } elseif ($response['question_type'] == 'true_false') {
          $answer = $answer == '1';
        }

        $formatted_participant['responses'][] = [
          'question_id' => $response['question_uuid'],
          'answer' => $answer
        ];
      }

      $formatted_participants[] = $formatted_participant;
    }

    return [
      'poll_id' => $poll->id(),
      'poll_title' => $poll->label(),
      'total_participants' => count($participants),
      'questions' => $formatted_questions,
      'participants' => $formatted_participants
    ];
  }


  private function addOrUpdateResponse(&$responses, $answer) {
    $answer_string = is_array($answer) ? json_encode($answer) : $answer;
    foreach ($responses as &$response) {
      if ($response['answer'] === $answer_string) {
        $response['count']++;
        return;
      }
    }
    $responses[] = ['answer' => $answer, 'count' => 1];
  }

}


