<?php
namespace Drupal\pipeline_drupal_actions\Plugin\ActionService;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\pipeline\Plugin\ActionServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * @ActionService(
 *   id = "article_created_email_notifier",
 *   label = @Translation("Article Created Email Notifier")
 * )
 */
class ArticleCreatedEmailNotifierService extends PluginBase implements ActionServiceInterface, ContainerFactoryPluginInterface
{

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new NewArticleCreatedService object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MailManagerInterface $mail_manager, EntityTypeManagerInterface $entity_type_manager)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mailManager = $mail_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.mail'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $configuration) {
    $form['to'] = [
      '#type' => 'email',
      '#title' => $this->t('To'),
      '#description' => $this->t('The email address of the recipient.'),
      '#default_value' => $configuration['to'] ?? '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    return [
      'to' => $form_state->getValue('to'),
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function executeAction(array $config, array &$context): string {
    try {
      $to = $config['configuration']['to'];

      $result = json_decode($context['last_response'], TRUE);
      $node_id = $result['nid'] ?? NULL;
      if (!$node_id) {
        throw new \Exception("Node ID not found in the last response.");
      }

      $node = $this->entityTypeManager->getStorage('node')->load($node_id);

      if (!$node) {
        throw new \Exception("Node not found with ID: {$node_id}");
      }

      $params = [
        'subject' => $this->t('New Article Created: @title', ['@title' => $node->getTitle()]),
        'title' => $node->getTitle(),
        'view_url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'edit_url' => $node->toUrl('edit-form', ['absolute' => TRUE])->toString(),
      ];

      $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

      $result = $this->mailManager->mail(
        'pipeline',
        'new_article_notification',
        $to,
        $langcode,
        $params
      );

      if ($result['result'] !== TRUE) {
        throw new \Exception('There was a problem sending the email notification.');
      }

      return "Email notification sent successfully to {$to} for new article (Node ID: {$node_id})";
    }
    catch (\Exception $e) {
      \Drupal::logger('pipeline')->error('Error in NewArticleActionService: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }
}
