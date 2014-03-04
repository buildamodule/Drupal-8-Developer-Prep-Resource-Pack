<?php

/**
 * @file
 * Contains \Drupal\Core\Form\BaseFormIdInterface.
 */

namespace Drupal\Core\Form;

/**
 * Provides an interface for a Form that has a base form ID.
 *
 * This will become the $form_state['build_info']['base_form_id'] used to
 * generate the name of hook_form_BASE_FORM_ID_alter().
 */
interface BaseFormIdInterface extends FormInterface {

  /**
   * Returns a string identifying the base form.
   *
   * @return string|false
   *   The string identifying the base form or FALSE if this is not a base form.
   */
  public function getBaseFormID();

}
