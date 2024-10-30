<?php
namespace Drupal\pipeline_drupal_actions\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class EntityCreationStrategyPass implements CompilerPassInterface
{
  public function process(ContainerBuilder $container)
  {
    if (!$container->has('pipeline_drupal_actions.entity_creation_strategy_manager')) {
      return;
    }

    $definition = $container->getDefinition('pipeline_drupal_actions.entity_creation_strategy_manager');
    $taggedServices = $container->findTaggedServiceIds('entity_creation_strategy');

    foreach ($taggedServices as $id => $tags) {
      $definition->addMethodCall('addStrategy', [new Reference($id)]);
    }
  }
}
