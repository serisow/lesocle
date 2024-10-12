<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pipeline\Entity\PipelineInterface;

class PipelineRunsForm extends FormBase {

  public function getFormId()
  {
    return 'pipeline_runs_form';
  }

  public static function getTitle(PipelineInterface $pipeline = NULL)
  {
    return t('Pipeline Runs: @label', ['@label' => $pipeline->label()]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, PipelineInterface $pipeline = NULL)
  {
    $form['runs'] = [
      '#type' => 'view',
      '#name' => 'pipeline_runs',
      '#display_id' => 'embed_1',
      '#arguments' => [$pipeline->id()],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // This form doesn't require a submit handler
  }
}
