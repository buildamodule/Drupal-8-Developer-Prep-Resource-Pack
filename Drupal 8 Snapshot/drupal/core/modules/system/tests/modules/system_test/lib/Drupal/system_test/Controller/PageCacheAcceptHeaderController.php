<?php

/**
 * @file
 * Contains \Drupal\system_test\Controller\PageCacheAcceptHeaderController.
 */

namespace Drupal\system_test\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Defines a controller to respond the page cache accept header test.
 */
class PageCacheAcceptHeaderController {

  /**
   * Processes a request that will vary with Accept header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return mixed
   */
  public function content(Request $request) {
    if ($request->headers->get('Accept') == 'application/json') {
      return new JsonResponse(array('content' => 'oh hai this is json'));
    }
    else {
      return "<p>oh hai this is html.</p>";
    }
  }
}

