<?php
namespace Drupal\participant\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

class ParticipantListBuilder extends EntityListBuilder {

  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['name'] = $this->t('Full Name');
    $header['first_name'] = $this->t('First Name');
    $header['last_name'] = $this->t('Last Name');
    $header['email'] = $this->t('Email');
    $header['poll_link'] = $this->t('Poll Link');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\participant\Entity\Participant $entity */
    $row['id'] = $entity->id();
    $row['name'] = $entity->toLink($entity->label());  // This now uses the combined first and last name
    $row['first_name'] = $entity->getFirstName();
    $row['last_name'] = $entity->getLastName();
    $row['email'] = $entity->getEmail();
    // Generate and add the poll link
    $accessToken = $entity->getAccessToken();
    $pollUrl = Url::fromRoute('poll.take', ['access_token' => $accessToken], ['absolute' => TRUE]);

    $row['poll_link'] = [
      'data' => [
        '#type' => 'link',
        '#title' => $this->t('Take Poll'),
        '#url' => $pollUrl,
        '#attributes' => ['target' => '_blank'],
      ],
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    // Add a 'View' operation
    $operations['view'] = [
      'title' => $this->t('View'),
      'weight' => 0,
      'url' => $entity->toUrl(),
    ];

    return $operations;
  }

}
