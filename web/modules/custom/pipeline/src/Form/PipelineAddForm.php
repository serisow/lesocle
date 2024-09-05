<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Controller for pipeline addition forms.
 */
class PipelineAddForm extends PipelineFormBase {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->messenger()->addMessage($this->t('Pipeline %name was created.', ['%name' => $this->entity->label()]));
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Create new pipeline');
    return $actions;
  }

}
