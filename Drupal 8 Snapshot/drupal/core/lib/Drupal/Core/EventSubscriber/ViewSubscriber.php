<?php

/**
 * @file
 * Definition of Drupal\Core\EventSubscriber\ViewSubscriber.
 */

namespace Drupal\Core\EventSubscriber;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Drupal\Core\ContentNegotiation;

/**
 * Main subscriber for VIEW HTTP responses.
 *
 * @todo This needs to get refactored to be extensible so that we can handle
 *   more than just Html and Drupal-specific JSON requests. See
 *   http://drupal.org/node/1594870
 */
class ViewSubscriber implements EventSubscriberInterface {

  protected $negotiation;

  public function __construct(ContentNegotiation $negotiation) {
    $this->negotiation = $negotiation;
  }

  /**
   * Processes a successful controller into an HTTP 200 response.
   *
   * Some controllers may not return a response object but simply the body of
   * one.  The VIEW event is called in that case, to allow us to mutate that
   * body into a Response object.  In particular we assume that the return
   * from an JSON-type response is a JSON string, so just wrap it into a
   * Response object.
   *
   * @param Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent $event
   *   The Event to process.
   */
  public function onView(GetResponseForControllerResultEvent $event) {

    $request = $event->getRequest();

    // For a master request, we process the result and wrap it as needed.
    // For a subrequest, all we want is the string value.  We assume that
    // is just an HTML string from a controller, so wrap that into a response
    // object.  The subrequest's response will get dissected and placed into
    // the larger page as needed.
    if ($event->getRequestType() == HttpKernelInterface::MASTER_REQUEST) {
      $method = 'on' . $this->negotiation->getContentType($request);

      if (method_exists($this, $method)) {
        $event->setResponse($this->$method($event));
      }
      else {
        $event->setResponse(new Response('Not Acceptable', 406));
      }
    }
    elseif ($request->attributes->get('_legacy')) {
      // This is an old hook_menu-based subrequest, which means we assume
      // the body is supposed to be the complete page.
      $page_result = $event->getControllerResult();
      if (!is_array($page_result)) {
        $page_result = array(
          '#markup' => $page_result,
        );
      }
      $event->setResponse(new Response(drupal_render_page($page_result)));
    }
    else {
      // This is a new-style Symfony-esque subrequest, which means we assume
      // the body is not supposed to be a complete page but just a page
      // fragment.
      $page_result = $event->getControllerResult();
      if (!is_array($page_result)) {
        $page_result = array(
          '#markup' => $page_result,
        );
      }
      $event->setResponse(new Response(drupal_render($page_result)));
    }
  }

  public function onJson(GetResponseForControllerResultEvent $event) {
    $page_callback_result = $event->getControllerResult();

    $response = new JsonResponse();
    $response->setData($page_callback_result);

    return $response;
  }

  public function onAjax(GetResponseForControllerResultEvent $event) {
    $page_callback_result = $event->getControllerResult();

    // Construct the response content from the page callback result.
    $commands = ajax_prepare_response($page_callback_result);
    $json = ajax_render($commands);

    // Build the actual response object.
    $response = new JsonResponse();
    $response->setContent($json);

    return $response;
  }

  public function onIframeUpload(GetResponseForControllerResultEvent $event) {
    $page_callback_result = $event->getControllerResult();

    // Construct the response content from the page callback result.
    $commands = ajax_prepare_response($page_callback_result);
    $json = ajax_render($commands);

    // Browser IFRAMEs expect HTML. Browser extensions, such as Linkification
    // and Skype's Browser Highlighter, convert URLs, phone numbers, etc. into
    // links. This corrupts the JSON response. Protect the integrity of the
    // JSON data by making it the value of a textarea.
    // @see http://malsup.com/jquery/form/#file-upload
    // @see http://drupal.org/node/1009382
    $html = '<textarea>' . $json . '</textarea>';

    return new Response($html);
  }

  /**
   * Processes a successful controller into an HTTP 200 response.
   *
   * Some controllers may not return a response object but simply the body of
   * one. The VIEW event is called in that case, to allow us to mutate that
   * body into a Response object. In particular we assume that the return from
   * an HTML-type response is a render array from a legacy page callback and
   * render it.
   *
   * @param Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent $event
   *   The Event to process.
   */
  public function onHtml(GetResponseForControllerResultEvent $event) {
    $page_callback_result = $event->getControllerResult();
    return new Response(drupal_render_page($page_callback_result));
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::VIEW][] = array('onView');

    return $events;
  }
}
