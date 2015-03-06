<?php

/**
 * @file
 * Contains \Drupal\blindd8\Blindd8ServiceProvider.
 */

namespace Drupal\blindd8;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Overrides our d8ing service.
 */
class Blindd8ServiceProvider extends ServiceProviderBase {
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('blindd8.blindd8ingservice');
    $definition->setClass('Drupal\blindd8\BlindD8ingService2');
  }
}
