<?php

use Symfony\Component\DependencyInjection;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

$var = Yaml::parse(__DIR__ . '/yaml-syntax.yml');
var_dump($var);
die();

$sc = new DependencyInjection\ContainerBuilder();

$sc->setParameter('charset', 'UTF-8');

$locator = new FileLocator(__DIR__);
$loader = new YamlFileLoader($locator);
$routes = $loader->load('routes.yml');
$sc->setParameter('routes', $routes);

$sc->register('context', 'Symfony\Component\Routing\RequestContext');
$sc->register('matcher', 'Symfony\Component\Routing\Matcher\UrlMatcher')
  ->setArguments(array('%routes%', new Reference('context')))
;
$sc->register('resolver', 'Symfony\Component\HttpKernel\Controller\ControllerResolver');

$sc->register('listener.router', 'Symfony\Component\HttpKernel\EventListener\RouterListener')
  ->setArguments(array(new Reference('matcher')))
;
$sc->register('listener.response', 'Symfony\Component\HttpKernel\EventListener\ResponseListener')
  ->setArguments(array('%charset%'))
;
$sc->register('listener.exception', 'Symfony\Component\HttpKernel\EventListener\ExceptionListener')
  ->setArguments(array('Calendar\\Controller\\ErrorController::exceptionAction'))
;
$sc->register('listener.string_response', 'Simplex\StringResponseListener');
$sc->register('dispatcher', 'Symfony\Component\EventDispatcher\EventDispatcher')
  ->addMethodCall('addSubscriber', array(new Reference('listener.router')))
  ->addMethodCall('addSubscriber', array(new Reference('listener.response')))
  ->addMethodCall('addSubscriber', array(new Reference('listener.exception')))
  ->addMethodCall('addSubscriber', array(new Reference('listener.string_response')))
;
$sc->register('framework', 'Simplex\Framework')
  ->setArguments(array(new Reference('dispatcher'), new Reference('resolver')))
;

return $sc;