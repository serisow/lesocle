<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\pipeline\Entity\PipelineInterface;

/**
 * Form for deleting a step type.
 */
class PipelineStepTypeDeleteForm extends ConfirmFormBase
{

  /**
   * The pipeline containing the step type to be deleted.
   *
   * @var \Drupal\pipeline\Entity\PipelineInterface
   */
  protected $pipeline;

  /**
   * The step type to be deleted.
   *
   * @var \Drupal\pipeline\Plugin\StepTypeInterface
   */
  protected $stepType;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the @step_type step type from the %pipeline pipeline?',
      ['%pipeline' => $this->pipeline->label(), '@step_type' => $this->stepType->label()]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->pipeline->toUrl('edit-form');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pipeline_step_type_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PipelineInterface $pipeline = NULL, $step_type = NULL) {
    $this->pipeline = $pipeline;
    $this->stepType = $this->pipeline->getStepType($step_type);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->pipeline->deleteStepType($this->stepType);
    $this->messenger()->addMessage($this->t('The step type %name has been deleted.', ['%name' => $this->stepType->label()]));
    $form_state->setRedirectUrl(Url::fromRoute('entity.pipeline.edit_steps', ['pipeline' => $this->pipeline->id()]));
  }

}
