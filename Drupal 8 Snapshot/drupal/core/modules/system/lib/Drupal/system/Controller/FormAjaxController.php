<?php

/**
 * @file
 * Contains \Drupal\system\FormAjaxController.
 */

namespace Drupal\system\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Defines a controller to respond to form Ajax requests.
 */
class FormAjaxController {

  /**
   * Processes an Ajax form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return mixed
   *   Whatever is returned by the triggering element's #ajax['callback']
   *   function. One of:
   *   - A render array containing the new or updated content to return to the
   *     browser. This is commonly an element within the rebuilt form.
   *   - A \Drupal\Core\Ajax\AjaxResponse object containing commands for the
   *     browser to process.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
   */
  public function content(Request $request) {
    list($form, $form_state) = $this->getForm($request);
    drupal_process_form($form['#form_id'], $form, $form_state);

    // We need to return the part of the form (or some other content) that needs
    // to be re-rendered so the browser can update the page with changed content.
    // Since this is the generic menu callback used by many Ajax elements, it is
    // up to the #ajax['callback'] function of the element (may or may not be a
    // button) that triggered the Ajax request to determine what needs to be
    // rendered.
    if (!empty($form_state['triggering_element'])) {
      $callback = $form_state['triggering_element']['#ajax']['callback'];
    }
    if (empty($callback) || !is_callable($callback)) {
      throw new HttpException(500, t('Internal Server Error'));
    }
    return call_user_func_array($callback, array(&$form, &$form_state));
  }

  /**
   * Gets a form submitted via #ajax during an Ajax callback.
   *
   * This will load a form from the form cache used during Ajax operations. It
   * pulls the form info from the request body.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return array
   *   An array containing the $form and $form_state. Use the list() function
   *   to break these apart:
   *   @code
   *     list($form, $form_state, $form_id, $form_build_id) = $this->getForm();
   *   @endcode
   *
   * @throws Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
   */
  protected function getForm(Request $request) {
    $form_state = form_state_defaults();
    $form_build_id = $request->request->get('form_build_id');

    // Get the form from the cache.
    $form = form_get_cache($form_build_id, $form_state);
    if (!$form) {
      // If $form cannot be loaded from the cache, the form_build_id must be
      // invalid, which means that someone performed a POST request onto
      // system/ajax without actually viewing the concerned form in the browser.
      // This is likely a hacking attempt as it never happens under normal
      // circumstances.
      watchdog('ajax', 'Invalid form POST data.', array(), WATCHDOG_WARNING);
      throw new BadRequestHttpException();
    }

    // Since some of the submit handlers are run, redirects need to be disabled.
    $form_state['no_redirect'] = TRUE;

    // When a form is rebuilt after Ajax processing, its #build_id and #action
    // should not change.
    // @see drupal_rebuild_form()
    $form_state['rebuild_info']['copy']['#build_id'] = TRUE;
    $form_state['rebuild_info']['copy']['#action'] = TRUE;

    // The form needs to be processed; prepare for that by setting a few internal
    // variables.
    $form_state['input'] = $request->request->all();
    $form_id = $form['#form_id'];

    return array($form, $form_state, $form_id, $form_build_id);
  }

}
