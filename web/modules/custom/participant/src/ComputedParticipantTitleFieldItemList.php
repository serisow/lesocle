<?php
namespace Drupal\participant;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field item list for Participant title.
 */
class ComputedParticipantTitleFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * Compute the values for the title field.
   */
  protected function computeValue() {
    /** @var \Drupal\participant\Entity\Participant $entity */
    $entity = $this->getEntity();
    // Ensure the entity is fully loaded
    if (!$entity->isNew()) {
      $entity = \Drupal::entityTypeManager()->getStorage('participant')->load($entity->id());
    }
    $this->list[0] = $this->createItem(0, $entity->getFirstName() . ' ' . $entity->getLastName());
  }
}
