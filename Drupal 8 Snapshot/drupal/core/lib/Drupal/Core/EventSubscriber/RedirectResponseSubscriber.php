<?php

/**
 * @file
 * Contains \Drupal\Core\EventSubscriber\RedirectResponseSubscriber.
 */

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Routing\PathBasedGeneratorInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Access subscriber for controller requests.
 */
class RedirectResponseSubscriber implements EventSubscriberInterface {

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\PathBasedGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs a RedirectResponseSubscriber object.
   *
   * @param \Drupal\Core\Routing\PathBasedGeneratorInterface $url_generator
   *   The url generator service.
   */
  public function __construct(PathBasedGeneratorInterface $url_generator) {
    $this->urlGenerator = $url_generator;
  }

  /**
   * Allows manipulation of the response object when performing a redirect.
   *
   * @param Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The Event to process.
   */
  public function checkRedirectUrl(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if ($response instanceOf RedirectResponse) {
      $options = array();

      $redirect_path = $response->getTargetUrl();
      $destination = $event->getRequest()->query->get('destination');
      // A destination in $_GET always overrides the current RedirectResponse.
      // We do not allow absolute URLs to be passed via $_GET, as this can be an
      // attack vector, with the following exception:
      // - Absolute URLs that point to this site (i.e. same base URL and
      //   base path) are allowed.
      if ($destination && (!url_is_external($destination) || _external_url_is_local($destination))) {
        $destination = drupal_parse_url($destination);

        $path = $destination['path'];
        $options['query'] = $destination['query'];
        $options['fragment'] = $destination['fragment'];
        // The 'Location' HTTP header must always be absolute.
        $options['absolute'] = TRUE;

        $response->setTargetUrl($this->urlGenerator->generateFromPath($path, $options));
      }
    }
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = array('checkRedirectUrl');
    return $events;
  }
}
