<?php
namespace Drupal\pipeline_integration;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\pipeline_integration\DependencyInjection\Compiler\EntityCreationStrategyPass;

/**
 * Class PipelineIntegrationServiceProvider
 */
class PipelineIntegrationServiceProvider extends ServiceProviderBase
{
  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container)
  {
    $container->addCompilerPass(new EntityCreationStrategyPass());
  }
}
