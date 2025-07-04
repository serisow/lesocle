<?php

namespace Drupal\pipeline\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Creates a form to delete an pipeline.
 */
class PipelineDeleteForm extends EntityDeleteForm {
  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Optionally select an pipeline before deleting %pipeline', ['%pipeline' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('The dependent configurations might need manual reconfiguration.');
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }
}
