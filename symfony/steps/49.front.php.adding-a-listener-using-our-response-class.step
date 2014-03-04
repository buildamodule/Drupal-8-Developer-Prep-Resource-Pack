<?php
 
require_once __DIR__ . '/vendor/autoload.php';
 
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing;
use Symfony\Component\HttpKernel;
use Symfony\Component\EventDispatcher\EventDispatcher;

$request = Request::createFromGlobals();
$routes = include __DIR__ . '/src/app.php';
 
$context = new Routing\RequestContext();
$context->fromRequest($request);
$matcher = new Routing\Matcher\UrlMatcher($routes, $context);
$resolver = new HttpKernel\Controller\ControllerResolver();

$dispatcher = new EventDispatcher();
$dispatcher->addListener('response', function (Simplex\ResponseEvent $event) {
  $response = $event->getResponse();
 
  if ($response->isRedirection()
    || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
    || 'html' !== $event->getRequest()->getRequestFormat()
  ) {
    return;
  }
 
  $response->setContent($response->getContent() . 'GA CODE');
});
 
$framework = new Simplex\Framework($dispatcher, $matcher, $resolver);
$response = $framework->handle($request);

$response->send();