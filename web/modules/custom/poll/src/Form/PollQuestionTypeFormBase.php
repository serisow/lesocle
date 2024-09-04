<?php
namespace Drupal\poll\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\poll\ConfigurableQuestionTypeInterface;
use Drupal\poll\Entity\PollInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Url;
use Drupal\poll\Plugin\QuestionTypeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a base form for question types.
 */
abstract class PollQuestionTypeFormBase extends FormBase {

  /**
   * The poll.
   *
   * @var \Drupal\poll\Entity\PollInterface
   */
  protected $poll;

  /**
   * The question type.
   *
   * @var QuestionTypeInterface|\Drupal\poll\ConfigurableQuestionTypeInterface
   */
  protected $questionType;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'question_type_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\poll\Entity\PollInterface $poll
   *   The poll.
   * @param string $question_type
   *   The question type ID.
   *
   * @return array
   *   The form structure.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function buildForm(array $form, FormStateInterface $form_state, PollInterface $poll = NULL, $question_type = NULL, $uuid = NULL) {
    $this->poll = $poll ?? $form_state->get('poll');

    $request = $this->getRequest();

   /*if (!($question_type instanceof ConfigurableQuestionTypeInterface)) {
      throw new NotFoundHttpException();
    }*/

    $form['#attached']['library'][] = 'poll/admin';
    $form['#attached']['library'][] = 'poll/question_type_modal';
    $form['#attributes']['class'][] = 'question-type-form';
    $form['#attached']['drupalSettings']['pollEditUrl'] = $this->poll->toUrl('edit-form')->toString();

    $form['id'] = [
      '#type' => 'value',
      '#value' => $question_type->getPluginId(),
    ];

    $form['data'] = [];
    $subform_state = SubformState::createForSubform($form['data'], $form, $form_state);
    $form['data'] = $question_type->buildConfigurationForm($form['data'], $subform_state);
    $form['data']['#tree'] = TRUE;

    // Check the URL for a weight, then the question type, otherwise use default.
    $form['weight'] = [
      '#type' => 'hidden',
      '#value' => $request->query->has('weight') ? (int) $request->query->get('weight'): $question_type->getWeight(),
    ];

    $form['uuid'] = [
      '#type' => 'hidden',
      '#value' => $question_type->getUuid(),
    ];
    $form['question_type'] = [
      '#type' => 'hidden',
      '#value' => $question_type->getPluginId(),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->poll->toUrl('edit-form'),
      '#attributes' => [
        'class' => ['button', 'dialog-cancel'],
        'data-dialog-type' => 'modal',
        'href' => '#',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate required fields
    $required_fields = ['question_text'];
    foreach ($required_fields as $field) {
      if (empty($form_state->getValue(['data', $field]))) {
        $form_state->setErrorByName("data][$field", $this->t('@field is required.', ['@field' => $form['data'][$field]['#title']]));
      }
    }

    // Call the question type plugin's validate method
    $this->questionType->validateConfigurationForm($form['data'], SubformState::createForSubform($form['data'], $form, $form_state));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state instanceof PollFormState && $form_state->isSkipResponse()) {
      $this->questionType->submitConfigurationForm($form['data'], SubformState::createForSubform($form['data'], $form, $form_state));

      $this->questionType->setWeight($form_state->getValue('weight'));
      if (!$this->questionType->getUuid()) {
        $this->poll->addQuestionType($this->questionType->getConfiguration());
      }
      $this->poll->save();
    }
  }

  /**
   * Get the poll entity.
   *
   * @return PollInterface|null The poll entity.
   *   The poll entity.
   */
  public function getPoll(): ?PollInterface {
    return $this->poll;
  }
}
