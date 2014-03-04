<?php

/**
 * @file
 * Definition of Drupal\Core\Ajax\CommandInterface.
 */

namespace Drupal\Core\Ajax;

/**
 * AJAX command interface.
 *
 * All AJAX commands passed to AjaxResponse objects should implement these
 * methods.
 */
interface CommandInterface {

  /**
   * Return an array to be run through json_encode and sent to the client.
   */
  public function render();
}
