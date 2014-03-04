<?php

/**
 * @file
 * Contains \Drupal\views_ui\Form\Ajax\ViewsFormInterface.
 */

namespace Drupal\views_ui\Form\Ajax;

use Drupal\Core\Form\FormInterface;
use Drupal\views\ViewStorageInterface;

interface ViewsFormInterface extends FormInterface {

  /**
   * Returns the key that represents this form.
   *
   * @return string
   *   The form key used in the URL, e.g., the string 'add-item' in
   *   'admin/structure/views/%/add-item/%/%/%'.
   */
  public function getFormKey();

  /**
   * Gets the form state for this form.
   *
   * @param \Drupal\views\ViewStorageInterface $view
   *   The view being edited.
   * @param string|null $display_id
   *   The display ID being edited, or NULL to load the first available display.
   * @param string $js
   *   If this is an AJAX form, it will be the string 'ajax'. Otherwise, it will
   *   be 'nojs'. This determines the response.
   *
   * @return array
   *   An associative array containing the current state of the form.
   */
  public function getFormState(ViewStorageInterface $view, $display_id, $js);

  /**
   * Creates a new instance of this form.
   *
   * @param \Drupal\views\ViewStorageInterface $view
   *   The view being edited.
   * @param string|null $display_id
   *   The display ID being edited, or NULL to load the first available display.
   * @param string $js
   *   If this is an AJAX form, it will be the string 'ajax'. Otherwise, it will
   *   be 'nojs'. This determines the response.
   *
   * @return array
   *   An form for a specific operation in the Views UI, or an array of AJAX
   *   commands to render a form.
   *
   * @todo When http://drupal.org/node/1843224 is in, this will return
   *   \Drupal\Core\Ajax\AjaxResponse instead of the array of AJAX commands.
   */
  public function getForm(ViewStorageInterface $view, $display_id, $js);

}
