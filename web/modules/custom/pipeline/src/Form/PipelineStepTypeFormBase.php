<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\pipeline\ConfigurableStepTypeInterface;
use Drupal\pipeline\Entity\PipelineInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Url;
use Drupal\pipeline\Plugin\StepTypeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a base form for step types.
 */
abstract class PipelineStepTypeFormBase extends FormBase {

  /**
   * The pipeline.
   *
   * @var \Drupal\pipeline\Entity\PipelineInterface
   */
  protected $pipeline;

  /**
   * The step type.
   *
   * @var StepTypeInterface|\Drupal\pipeline\ConfigurableStepTypeInterface
   */
  protected $stepType;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'step_type_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\pipeline\Entity\PipelineInterface $pipeline
   *   The pipeline.
   * @param string $step_type
   *   The step type ID.
   *
   * @return array
   *   The form structure.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function buildForm(array $form, FormStateInterface $form_state, PipelineInterface $pipeline = NULL, $step_type = NULL, $uuid = NULL) {
    $this->pipeline = $pipeline ?? $form_state->get('pipeline');

    $request = $this->getRequest();

   /*if (!($step_type instanceof ConfigurableStepTypeInterface)) {
      throw new NotFoundHttpException();
    }*/

    $form['#attached']['library'][] = 'pipeline/admin';
    $form['#attached']['library'][] = 'pipeline/step_type_modal';
    $form['#attributes']['class'][] = 'step-type-form';
    $form['#attached']['drupalSettings']['pipelineEditUrl'] = $this->pipeline->toUrl('edit-form')->toString();

    $form['id'] = [
      '#type' => 'value',
      '#value' => $step_type->getPluginId(),
    ];

    $form['data'] = [];
    $subform_state = SubformState::createForSubform($form['data'], $form, $form_state);
    $form['data'] = $step_type->buildConfigurationForm($form['data'], $subform_state);
    $form['data']['#tree'] = TRUE;

    // Check the URL for a weight, then the step type, otherwise use default.
    $form['weight'] = [
      '#type' => 'hidden',
      '#value' => $request->query->has('weight') ? (int) $request->query->get('weight'): $step_type->getWeight(),
    ];

    $form['uuid'] = [
      '#type' => 'hidden',
      '#value' => $step_type->getUuid(),
    ];
    $form['step_type'] = [
      '#type' => 'hidden',
      '#value' => $step_type->getPluginId(),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->pipeline->toUrl('edit-form'),
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
    $required_fields = [];
    foreach ($required_fields as $field) {
      if (empty($form_state->getValue(['data', $field]))) {
        $form_state->setErrorByName("data][$field", $this->t('@field is required.', ['@field' => $form['data'][$field]['#title']]));
      }
    }

    // Call the step type plugin's validate method
    $this->stepType->validateConfigurationForm($form['data'], SubformState::createForSubform($form['data'], $form, $form_state));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state instanceof PipelineFormState && $form_state->isSkipResponse()) {
      $this->stepType->submitConfigurationForm($form['data'], SubformState::createForSubform($form['data'], $form, $form_state));

      $this->stepType->setWeight($form_state->getValue('weight'));
      if (!$this->stepType->getUuid()) {
        $this->pipeline->addStepType($this->stepType->getConfiguration());
      }
      $this->pipeline->save();
    }
  }

  /**
   * Get the pipeline entity.
   *
   * @return PipelineInterface|null The pipeline entity.
   *   The pipeline entity.
   */
  public function getPipeline(): ?PipelineInterface {
    return $this->pipeline;
  }
}
