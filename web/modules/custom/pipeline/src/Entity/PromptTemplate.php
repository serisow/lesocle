<?php
namespace Drupal\pipeline\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Prompt Template entity.
 *
 * @ConfigEntityType(
 *   id = "prompt_template",
 *   label = @Translation("Prompt Template"),
 *   handlers = {
 *     "list_builder" = "Drupal\pipeline\PromptTemplateListBuilder",
 *     "form" = {
 *       "add" = "Drupal\pipeline\Form\PromptTemplateForm",
 *       "edit" = "Drupal\pipeline\Form\PromptTemplateForm",
 *       "delete" = "Drupal\pipeline\Form\PromptTemplateDeleteForm"
 *     },
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *   },
 *   config_prefix = "prompt_template",
 *   admin_permission = "administer prompt templates",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "collection" = "/admin/config/pipeline/prompt-templates",
 *     "canonical" = "/admin/config/prompt_template/{prompt_template}",
 *     "add-form" = "/admin/config/pipeline/prompt-templates/add",
 *     "edit-form" = "/admin/config/pipeline/prompt-templates/{prompt_template}/edit",
 *     "delete-form" = "/admin/config/pipeline/prompt-templates/{prompt_template}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "template",
 *     "output_format"
 *   }
 * )
 */
class PromptTemplate extends ConfigEntityBase {

  /**
   * The Prompt Template ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Prompt Template label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Prompt Template description.
   *
   * @var string
   */
  protected $description;

  /**
   * The prompt template content.
   *
   * @var string
   */
  protected $template;

  /**
   * The expected output format (e.g., 'json', 'html', 'text').
   *
   * @var string
   */
  protected $output_format;

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->label;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getTemplate() {
    return $this->template;
  }

  public function setTemplate($template) {
    $this->template = $template;
    return $this;
  }

  public function getOutputFormat() {
    return $this->output_format;
  }

  public function setOutputFormat($output_format) {
    $this->output_format = $output_format;
    return $this;
  }
}
