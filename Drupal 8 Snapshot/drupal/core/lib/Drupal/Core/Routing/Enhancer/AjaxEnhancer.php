<?php

/**
 * @file
 * Contains \Drupal\Core\Routing\Enhancer\AjaxEnhancer.
 */

namespace Drupal\Core\Routing\Enhancer;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Cmf\Component\Routing\Enhancer\RouteEnhancerInterface;
use Drupal\Core\ContentNegotiation;

/**
 * Enhances an ajax route with the appropriate controller.
 */
class AjaxEnhancer implements RouteEnhancerInterface {

  /**
   * Content negotiation library.
   *
   * @var \Drupal\CoreContentNegotiation
   */
  protected $negotiation;

  /**
   * Constructs a new \Drupal\Core\Routing\Enhancer\AjaxEnhancer object.
   *
   * @param \Drupal\Core\ContentNegotiation $negotiation
   *   The Content Negotiation service.
   */
  public function __construct(ContentNegotiation $negotiation) {
    $this->negotiation = $negotiation;
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    if (empty($defaults['_content']) && $this->negotiation->getContentType($request) == 'drupal_ajax') {
      $defaults['_content'] = isset($defaults['_controller']) ? $defaults['_controller'] : NULL;
      $defaults['_controller'] = '\Drupal\Core\Controller\AjaxController::content';
    }
    return $defaults;
  }
}
