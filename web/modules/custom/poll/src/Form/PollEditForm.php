<?php
namespace Drupal\poll\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormState;
use Drupal\poll\ConfigurableQuestionTypeInterface;
use Drupal\poll\Entity\PollInterface;
use Drupal\poll\Plugin\QuestionTypeManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\poll\Service\PollDataService;
use Drupal\poll\Service\LLMAnalysisService;


/**
 * Controller for poll edit form.
 */
class PollEditForm extends PollFormBase {
  /**
   * The question type manager service.
   *
   * @var \Drupal\poll\Plugin\QuestionTypeManager
   */
  protected $questionTypeManager;

  protected $formBuilder;

  /**
   * The poll data service.
   *
   * @var \Drupal\poll\Service\PollDataService
   */
  protected $pollDataService;

  /**
   * The LLM analysis service.
   *
   * @var \Drupal\poll\Service\LLMAnalysisService
   */
  protected $llmAnalysisService;

  /**
   * Constructs an PollEditForm object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $poll_storage
   *   The storage.
   * @param \Drupal\poll\Plugin\QuestionTypeManager $question_type_manager
   *   The question type manager service.
   * @param \Drupal\poll\Service\PollDataService $poll_data_service
   *    The poll data service.
   * @param \Drupal\poll\Service\LLMAnalysisService $llm_analysis_service
   *    The LLM analysis service.
   */
  public function __construct(
    EntityStorageInterface $poll_storage,
    QuestionTypeManager $question_type_manager,
    FormBuilder $form_builder,
    PollDataService $poll_data_service,
    LLMAnalysisService $llm_analysis_service
  ) {
    parent::__construct($poll_storage);
    $this->questionTypeManager = $question_type_manager;
    $this->formBuilder = $form_builder;
    $this->pollDataService = $poll_data_service;
    $this->llmAnalysisService = $llm_analysis_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('poll'),
      $container->get('plugin.manager.question_type'),
      $container->get('form_builder'),
      $container->get('poll.poll_data_service'),
      $container->get('poll.llm_analysis_service')
    );
  }

  public function getFormId() {
    return 'poll_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    // Add the button close the poll
    //  test set set close manually
    $this->entity->close();
    if (!$this->entity->isClosed()) {
      $form['actions']['close'] = [
        '#type' => 'submit',
        '#value' => $this->t('Close Poll and Analyze'),
        '#submit' => ['::closePoll'],
        '#weight' => 5,
      ];
    }
    $form['#title'] = $this->t('Edit poll %name', ['%name' => $this->entity->label()]);
    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'poll/admin';
    $form['#attached']['library'][] = 'poll/question_type_modal';
    $form['#attached']['drupalSettings']['pollId'] = $this->entity->id();

    // Determine which tab we're on
    $route_name = $this->getRouteMatch()->getRouteName();
    if ($route_name == 'entity.poll.edit_form') {
      // General Information tab
    } elseif ($route_name == 'entity.poll.edit_questions') {
      // Questions tab
      // Remove the fields from PollFormBase as they're not needed on this tab
      unset($form['label'], $form['id'], $form['instructions'], $form['status'], $form['langcode']);
      // Build the list of existing question types for this poll.
      $form['question_types'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('N°'),
          $this->t('Question'),
          $this->t('Question Type'),
          $this->t('Weight'),
          $this->t('Operations'),
        ],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'poll-question-type-order-weight',
          ],
        ],
        '#attributes' => [
          'id' => 'poll-question-types',
          'class' => ['poll-questions-table'],
        ],
        '#empty' => t('There are currently no question types in this poll. Add one by selecting an option below.'),
        // Render question types below parent elements.
        '#weight' => 5,
      ];

      // Build the new question type addition form and add it to the question type list.
      $new_question_type_options = [];
      $question_types = $this->questionTypeManager->getDefinitions();
      uasort($question_types, function ($a, $b) {
        return strcasecmp($a['id'], $b['id']);
      });
      foreach ($question_types as $question_type => $definition) {
        $new_question_type_options[$question_type] = $definition['label'];
      }

      // Add the "new question" row
      $form['add_question'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['poll-new-question-row']],
        '#weight' => 4, // Ensure it's placed just before the question_types table
      ];

      $form['add_question']['question_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Question type'),
        '#title_display' => 'invisible',
        '#options' => $new_question_type_options,
        '#empty_option' => $this->t('- Select a question type -'),
        '#attributes' => ['aria-label' => $this->t('Select a question type')],
      ];

      $form['add_question']['add'] = [
        '#type' => 'button',
        '#value' => $this->t('Add Question'),
        '#ajax' => [
          'callback' => '::openAddQuestionModal',
          'event' => 'click',
        ],
        '#attributes' => ['class' => ['button--primary']],
      ];

      // Construct the questions rows.
      $question_number = 1;
      foreach ($this->entity->getQuestionTypes() as $question_type) {
        $uuid = $question_type->getUuid();
        $form['question_types'][$uuid] = [
          '#attributes' => ['class' => ['draggable']],
          'number' => ['#plain_text' => $question_number],
          'question_text' => ['#plain_text' => $this->trimText($question_type->getConfiguration()['data']['question_text'] ?? '', 50)],
          'question_type' => ['#plain_text' => $question_type->label()],
          'weight' => [
            '#type' => 'weight',
            '#title' => $this->t('Weight for @title', ['@title' => $question_type->label()]),
            '#title_display' => 'invisible',
            '#default_value' => $question_type->getWeight(),
            '#attributes' => ['class' => ['poll-question-type-order-weight']],
          ],
          'operations' => [
            '#type' => 'operations',
            '#links' => $this->getOperations($question_type),
          ],
        ];
        $question_number++;
      }
    } elseif ($route_name == 'entity.poll.participants') {
      // Participants tab
      $form = $this->formBuilder()->getForm('Drupal\participant\Form\ParticipantBulkAddForm', $this->entity->id());
      return $form;
    }
    $form['#attributes']['data-action-url'] = $this->entity->toUrl('edit-form')->toString();
    return $form;
  }

  /**
   * Returns the number of participants for this poll.
   *
   * @return int
   *   The number of participants.
   */
  protected function getParticipantCount() {
    return count($this->entity->getParticipants());
  }
  public function validateAddQuestionType(array &$form, FormStateInterface $form_state) {
    $new_question_type = $form_state->getValue(['add_question', 'question_type']);
    if (empty($new_question_type)) {
      $form_state->setErrorByName('add_question][question_type', $this->t('Select a question type to add.'));
    }
  }

  /**
   * Submit handler for question type.
   */
  public function addQuestionType($form, FormStateInterface $form_state) {
    $new_question_type = $form_state->getValue(['add_question', 'question_type']);
    if (!$new_question_type) {
      $form_state->setErrorByName('add_question][question_type', $this->t('Select a question type to add.'));
      return;
    }
    // Calculate the weight for the new question type
    $weight = count($this->entity->getQuestionTypes());

    // Check if this field has any configuration options.
    $question_type = $this->questionTypeManager->getDefinition($new_question_type);

    // Load the configuration form for this option.
    if (is_subclass_of($question_type['class'], '\Drupal\poll\ConfigurableQuestionTypeInterface')) {
      $form_state->setRedirect(
        'poll.question_type_add_form',
        [
          'poll' => $this->entity->id(),
          'question_type' => $new_question_type,
        ],
        ['query' => ['weight' => $weight]]
      );
    } // If there's no form, immediately add the question type.
    else {
      $question_type = [
        'id' => $question_type['id'],
        'data' => [],
        'weight' => $form_state->getValue('weight'),
      ];
      $question_type_id = $this->entity->addQuestionType($question_type);
      $this->entity->save();
      if (!empty($question_type_id)) {
        $this->messenger()->addMessage($this->t('The Poll question type was successfully applied.'));
      }
    }
  }

  public function openAddQuestionModal(array &$form, FormStateInterface $form_state) {
    $question_type = $form_state->getValue(['add_question', 'question_type']);

    $response = new AjaxResponse();

    if ($question_type) {
      $form_state = (new FormState())
        ->set('poll', $this->entity)
        ->set('question_type', $question_type);
      $form = $this->formBuilder->buildForm('Drupal\poll\Form\PollQuestionTypeAddForm', $form_state);
      $form['actions']['submit']['#submit'] = ['::handleSubmit'];

      // Make the dialog title
      $question_type_manager = \Drupal::service('plugin.manager.question_type');
      $plugin_definition = $question_type_manager->getDefinition($question_type);
      $dialog_title = $plugin_definition['label'];


      $response->addCommand(new OpenModalDialogCommand(
        $this->t('Add @type', ['@type' => $dialog_title]),
        $form,
        [
          'width' => 'auto',
          'dialogClass' => 'poll-question-type-add-modal',
        ]
      ));
    } else {
      $response->addCommand(new ReplaceCommand('#edit-add-question', $form['add_question']));
    }

    return $response;
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $question_types = $form_state->getValue('question_types');
    if (!empty($question_types)) {
      foreach ($question_types as $uuid => $item) {
        if ($uuid !== 'new' && isset($item['weight'])) {
          $this->entity->getQuestionType($uuid)->setWeight($item['weight']);
        }
      }
      $this->entity->save();
    }
    // Check if we're on the Questions tab
    $route_name = $this->getRouteMatch()->getRouteName();
    if ($route_name == 'entity.poll.edit_questions') {
      // Redirect back to the Questions tab
      $form_state->setRedirect('entity.poll.edit_questions', ['poll' => $this->entity->id()]);
    } else {
      // Otherwise, use the default redirect (likely the edit form)
      $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $this->messenger()->addMessage($this->t('Changes to the poll have been saved.'));
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Update poll');

    return $actions;
  }

  /**
   * Trims text to a certain length and adds ellipsis if needed.
   *
   * @param string $text
   *   The text to trim.
   * @param int $length
   *   The maximum length of the trimmed string.
   *
   * @return string
   *   The trimmed text.
   */
  private function trimText($text, $length) {
    if (strlen($text) > $length) {
      return substr($text, 0, $length - 3) . '...';
    }
    return $text;
  }

  protected function getOperations($question_type) {
    $links = [];
    $is_configurable = $question_type instanceof ConfigurableQuestionTypeInterface;
    if ($is_configurable) {
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('poll.question_type_edit_form', [
          'poll' => $this->entity->id(),
          'question_type' => $question_type->getPluginId(),
          'uuid' => $question_type->getUuid(),
        ]),
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(
            [
              'width' => 'auto',
              'dialogClass' => 'poll-question-type-edit-modal',
            ]),
        ],
      ];
    }
    $links['delete'] = [
      'title' => $this->t('Delete'),
      'url' => Url::fromRoute('poll.question_type_delete', [
        'poll' => $this->entity->id(),
        'question_type' => $question_type->getUuid(),
      ]),
    ];
    return $links;
  }


  /**
   * Form submission handler for closing the poll.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function closePoll(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\poll\Entity\Poll $poll */
    $poll = $this->entity;

    // Close the poll
    $poll->close();
    $poll->setClosedDate(time());

    // Fetch poll data
    $pollData = $this->pollDataService->getPollData($poll->id());

    // Perform LLM analysis
    $analysis = $this->llmAnalysisService->analyzePolls($pollData);

    // Store the analysis results
    $poll->setLlmAnalysis($analysis);

    // Save the updated poll entity
    $poll->save();

    $this->messenger()->addMessage($this->t('Poll closed and analysis completed.'));
    $form_state->setRedirect('entity.poll.collection');
  }

  /**
   * Process the LLM analysis results for storage.
   *
   * @param array $analysis
   *   The raw analysis results from the LLM.
   *
   * @return array
   *   The processed analysis results.
   */
  protected function processAnalysisResults(array $analysis) {
    // Here you can add any additional processing needed before storing the results
    // For example, you might want to sanitize the data, extract specific information, etc.
    return [
      'raw_response' => json_encode($analysis),
      'processed_data' => json_encode($this->extractRelevantData($analysis)),
    ];
  }

  /**
   * Extract relevant data from the LLM analysis for easier frontend consumption.
   *
   * @param array $analysis
   *   The raw analysis results from the LLM.
   *
   * @return array
   *   The extracted relevant data.
   */
  protected function extractRelevantData(array $analysis) {
    // Implement the logic to extract and structure the data as needed for the frontend
    // This is just a placeholder implementation
    $relevantData = [];
    foreach ($analysis as $insight) {
      $relevantData[] = [
        'insight' => $insight['insight'],
        'chart_type' => $insight['chart_type'],
        'data' => $insight['data'],
      ];
    }
    return $relevantData;
  }
}
