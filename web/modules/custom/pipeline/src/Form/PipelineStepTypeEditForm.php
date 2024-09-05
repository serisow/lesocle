<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Entity\PipelineInterface;
use Drupal\pipeline\Plugin\StepTypeInterface;

class PipelineStepTypeEditForm extends PipelineStepTypeFormBase {
  public function getFormId() {
    return 'pipeline_step_type_edit_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PipelineInterface $pipeline = NULL, $step_type = NULL, $uuid = NULL) {
    // If $pipeline is not passed as an argument, try to get it from form state
    if ($pipeline === NULL) {
      $pipeline = $form_state->get('pipeline');
    }

    // If we still don't have $pipeline, log an error and return an empty form
    if ($pipeline === NULL) {
      \Drupal::logger('pipeline')->error('Pipeline object not found in PipelineStepTypeEditForm::buildForm');
      return $form;
    }

    $this->pipeline = $pipeline;

    // Prepare step_type
    $step_type = $step_type ?? $form_state->get('step_type');
    $uuid = $uuid ?? $form_state->get('uuid');
    $this->stepType = $this->pipeline->getStepType($uuid);


    $form = parent::buildForm($form, $form_state, $pipeline, $this->stepType, $uuid);
    $form['#title'] = $this->t('Edit @type', ['@type' => $this->stepType->label()]);
    // Check if the form is being loaded in a modal
    if ($this->getRequest()->query->get('modal')) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
      ];
    }
    $form['actions']['submit']['#value'] = $this->t('Update step type');
    $form['#action'] = $pipeline->toUrl('edit-form')->toString();

    $form['#attributes']['data-dialog-form'] = 'true';
    return $form;
  }
  protected function prepareStepType(?string $step_type_id) {
    return $this->pipeline->getStepType($step_type_id);
  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    // The form is already processed in the controller, so we just need to return NULL
    // to prevent Drupal from trying to generate an AJAX response here
    return NULL;
  }
}
