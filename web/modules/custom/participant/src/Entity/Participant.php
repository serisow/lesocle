<?php
namespace Drupal\participant\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * @ContentEntityType(
 *   id = "participant",
 *   label = @Translation("Participant"),
 *   label_collection = @Translation("Participants"),
 *   label_singular = @Translation("participant"),
 *   label_plural = @Translation("participants"),
 *   base_table = "participant",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "owner" = "author",
 *     "published" = "published",
 *     "label" = "title"
 *   },
 *   translatable = TRUE,
 *   data_table = "participant_field_data",
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *     "list_builder" = "Drupal\participant\Controller\ParticipantListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\participant\Form\ParticipantForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\participant\Form\ParticipantDeleteForm",
 *     },
 *   },
 *   links = {
 *     "canonical" = "/participant/{participant}",
 *     "add-form" = "/admin/content/participants/add",
 *     "edit-form" = "/admin/content/participants/manage/{participant}/edit",
 *     "delete-form" = "/admin/content/participants/manage/{participant}/delete",
 *     "collection" = "/admin/content/participants",
 *   },
 *   admin_permission = "administer participant",
 *   field_ui_base_route = "entity.participant.settings"
 *
 * )
 */
class Participant extends ContentEntityBase implements EntityOwnerInterface, EntityPublishedInterface {
  use EntityOwnerTrait, EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += self::ownerBaseFieldDefinitions($entity_type);
    $fields += self::publishedBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The computed title of the participant.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\participant\ComputedParticipantTitleFieldItemList');

    $fields['first_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('First Name'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 1,
      ]);

    $fields['last_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Last Name'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 2,
      ]);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'email_default',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'email_mailto',
        'weight' => 3,
      ]);

    $fields['poll'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Poll'))
      ->setDescription(t('The poll this participant is associated with.'))
      ->setSetting('target_type', 'poll')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 4,
      ]);

    $fields['access_token'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Access Token'))
      ->setDescription(t('Token used for generating unique access URL.'))
      ->setRequired(TRUE)
      ->setDefaultValueCallback(static::class . '::generateUuid')
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 5,
      ]);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The status of the participant.'))
      ->setSettings([
        'allowed_values' => [
          'pending' => 'Pending',
          'started' => 'Started',
          'completed' => 'Completed',
          'expired' => 'Expired',
        ],
      ])
      ->setDefaultValue('pending')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 6,
      ]);
    $fields['last_access'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last Access'))
      ->setDescription(t('The time that the participant last accessed the poll.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 7,
      ]);
    $fields['poll_responses'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Poll Responses'))
      ->setDescription(t('Structured storage of poll responses'))
      ->setTranslatable(FALSE);

    return $fields;
  }

  /**
   * Gets the title of the participant.
   *
   * @return string
   *   The title of the participant.
   */
  public function getTitle(): string {
    return $this->get('title')->value;
  }

  /**
   * Gets the first name of the participant.
   *
   * @return string
   *   The first name of the participant.
   */
  public function getFirstName(): string {
    return $this->get('first_name')->value;
  }

  /**
   * Gets the last name of the participant.
   *
   * @return string
   *   The last name of the participant.
   */
  public function getLastName(): string {
    return $this->get('last_name')->value;
  }

  /**
   * Gets the email of the participant.
   *
   * @return string
   *   The email of the participant.
   */
  public function getEmail(): string {
    return $this->get('email')->value;
  }

  /**
   * Gets the poll associated with the participant.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The poll entity, or null if not set.
   */
  public function getPoll() {
    return $this->get('poll')->entity;
  }

  /**
   * Gets the access token of the participant.
   *
   * @return string
   *   The access token of the participant.
   */
  public function getAccessToken(): string {
    return $this->get('access_token')->value;
  }

  /**
   * Sets the access token of the participant.
   *
   * @param string $access_token
   *   The access token to set.
   *
   * @return $this
   */
  public function setAccessToken(string $access_token): self {
    $this->set('access_token', $access_token);
    return $this;
  }

  /**
   * Gets the status of the participant.
   *
   * @return string
   *   The status of the participant.
   */
  public function getStatus(): string {
    return $this->get('status')->value;
  }

  /**
   * Sets the status of the participant.
   *
   * @param string $status
   *   The status to set.
   *
   * @return $this
   */
  public function setStatus(string $status): self {
    $this->set('status', $status);
    return $this;
  }

  /**
   * Gets the last access timestamp of the participant.
   *
   * @return int
   *   The last access timestamp of the participant.
   */
  public function getLastAccess(): int {
    return $this->get('last_access')->value;
  }

  /**
   * Sets the last access timestamp of the participant.
   *
   * @param int $timestamp
   *   The last access timestamp to set.
   *
   * @return $this
   */
  public function setLastAccess(int $timestamp): self {
    $this->set('last_access', $timestamp);
    return $this;
  }

  // Then add these methods to the Participant class:
  public function getComputedTitle() {
    return $this->getFirstName() . ' ' . $this->getLastName();
  }

  public function setComputedTitle($title) {
    // Do nothing, as this is a computed field
  }

  public static function generateUuid() {
    return \Drupal::service('uuid')->generate();
  }

  public function getFrontendPollUrl() {
    $config = \Drupal::config('poll.settings');
    $frontend_base_url = $config->get('frontend_base_url');
    $access_token = $this->getAccessToken();

    if ($frontend_base_url && $access_token) {
      return $frontend_base_url . '/poll/' . $access_token;
    }

    return NULL;
  }

  public function hasCompletedPoll($pollId) {
    $pollResponses = $this->get('poll_responses')->first()?->getValue() ?: [];
    return !empty($pollResponses) && $this->get('poll')->target_id == $pollId && in_array($this->getStatus(), ['completed', 'expired']);
  }

  /**
   * {@inheritdoc}
   * @param EntityStorageInterface $storage
   * @param true $update
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    // Invalidate the poll's participant cache tag.
    if ($poll_id = $this->get('poll')->target_id) {
      \Drupal::service('cache_tags.invalidator')->invalidateTags(['poll:' . $poll_id . ':participants']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    // This fix the bug of Participant(N) in the poll edit from.
    foreach ($entities as $entity) {
      if ($poll_id = $entity->get('poll')->target_id) {
        \Drupal::service('cache_tags.invalidator')->invalidateTags(['poll:' . $poll_id . ':participants']);
      }
    }
  }
}
