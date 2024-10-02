<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PipelineFilterForm extends FormBase {
  /**
   * A language Manager.
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new EntityListBuilder object.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pipeline_filter_form';
  }

  /**
   * Builds the filter form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'pipeline-filter-form';
    $form['#attached']['library'][] = 'pipeline/admin';

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#size' => 30,
      '#default_value' => $this->getRequest()->query->get('title'),
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Enabled'),
        '0' => $this->t('Disabled'),
      ],
      '#default_value' => $this->getRequest()->query->get('status'),
    ];

    $languages = $this->languageManager->getLanguages();
    $language_options = ['' => $this->t('- Any -')];
    foreach ($languages as $langcode => $language) {
      $language_options[$langcode] = $language->getName();
    }

    $form['langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $language_options,
      '#default_value' => $this->getRequest()->query->get('langcode'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
    ];

    return $form;
  }

  /**
   * Handles form submission for filtering.
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Collect filter values and redirect with query parameters.
    $query = [];
    if ($title = $form_state->getValue('title')) {
      $query['title'] = $title;
    }
    if ($status = $form_state->getValue('status') !== '') {
      $query['status'] = $form_state->getValue('status');
    }
    if (($langcode = $form_state->getValue('langcode')) !== '') {
      $query['langcode'] = $langcode;
    }
    $form_state->setRedirect('entity.pipeline.collection', [], ['query' => $query]);
  }

  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->setRedirect('entity.pipeline.collection');
  }

}
