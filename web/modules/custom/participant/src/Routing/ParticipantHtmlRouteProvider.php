<?php

namespace Drupal\participant\Routing;

use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for the "participant" entity.
 */
class ParticipantHtmlRouteProvider extends DefaultHtmlRouteProvider {
  /**
   * {@inheritdoc}
   */
  protected function getEntityRoutes($entity_type): array|RouteCollection
  {
    $routes = parent::getRoutes($entity_type);
    return $routes;
  }

}
