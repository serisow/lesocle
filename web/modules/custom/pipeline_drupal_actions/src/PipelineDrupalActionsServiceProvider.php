<?php
namespace Drupal\pipeline_drupal_actions;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\pipeline_drupal_actions\DependencyInjection\Compiler\EntityCreationStrategyPass;

/**
 * Class PipelineDrupalActionsServiceProvider
 */
class PipelineDrupalActionsServiceProvider extends ServiceProviderBase
{
  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container)
  {
    $container->addCompilerPass(new EntityCreationStrategyPass());
  }
}
