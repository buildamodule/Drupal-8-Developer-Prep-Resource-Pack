<?php

/**
 * @file
 * Contains Drupal\Core\Routing\UrlMatcher.
 */

namespace Drupal\Core\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use Symfony\Cmf\Component\Routing\NestedMatcher\UrlMatcher as BaseUrlMatcher;

/**
 * Drupal-specific URL Matcher; handles the Drupal "system path" mapping.
 */
class UrlMatcher extends BaseUrlMatcher {

  /**
   * Constructs a new UrlMatcher.
   *
   * The parent class has a constructor we need to skip, so just override it
   * with a no-op.
   */
  public function __construct() {}

  public function finalMatch(RouteCollection $collection, Request $request) {
    $this->routes = $collection;
    $context = new RequestContext();
    $context->fromRequest($request);
    $this->setContext($context);
    return $this->match('/' . $request->attributes->get('_system_path'));
  }

}
