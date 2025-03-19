<?php

namespace Drupal\pipeline\StepHandler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manages step handler services.
 */
class StepHandlerManager implements ContainerInjectionInterface {

  /**
   * The step handlers.
   *
   * @var array
   */
  protected $handlers = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    
    // Add all step handlers
    $instance->addHandler('llm_step', $container->get('pipeline.step_handler.llm'));
    $instance->addHandler('action_step', $container->get('pipeline.step_handler.action'));
    $instance->addHandler('google_search', $container->get('pipeline.step_handler.google_search'));
    $instance->addHandler('news_api_search', $container->get('pipeline.step_handler.news_api'));
    $instance->addHandler('social_media_step', $container->get('pipeline.step_handler.social_media'));
    $instance->addHandler('upload_image_step', $container->get('pipeline.step_handler.upload_image'));
    $instance->addHandler('upload_audio_step', $container->get('pipeline.step_handler.upload_audio'));
    $instance->addHandler('image_enrichment_step', $container->get('pipeline.step_handler.image_enrichment'));
    
    return $instance;
  }

  /**
   * Adds a step handler.
   *
   * @param string $type
   *   The step type.
   * @param \Drupal\pipeline\StepHandler\StepHandlerInterface $handler
   *   The step handler.
   */
  public function addHandler($type, StepHandlerInterface $handler) {
    $this->handlers[$type] = $handler;
  }

  /**
   * Gets a step handler.
   *
   * @param string $type
   *   The step type.
   *
   * @return \Drupal\pipeline\StepHandler\StepHandlerInterface
   *   The step handler.
   */
  public function getHandler($type) {
    if (isset($this->handlers[$type])) {
      return $this->handlers[$type];
    }
    
    // Return a default handler if the requested type doesn't exist
    return new DefaultStepHandler();
  }
}
