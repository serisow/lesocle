<?php

namespace Drupal\poll\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\poll\Entity\PollInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;

class PollRedirectController extends ControllerBase {

  protected $configFactory;
  protected $urlGenerator;

  public function __construct(ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator) {
    $this->configFactory = $config_factory;
    $this->urlGenerator = $url_generator;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('url_generator')
    );
  }

  public function redirectToInsight(PollInterface $poll) {
    $config = $this->configFactory->get('poll.settings');
    $frontend_base_url = $config->get('frontend_base_url');

    if (!$frontend_base_url) {
      $this->messenger()->addError($this->t('Frontend URL is not configured.'));
      return $this->redirect('entity.poll.collection');
    }

    $url = $frontend_base_url . '/poll-analysis/' . $poll->id();

    // Create a TrustedRedirectResponse
    $response = new TrustedRedirectResponse($url);

    // Add the URL to the response's cacheability metadata
    $response->addCacheableDependency($url);

    return $response;
  }
}
