<?php

/**
 * @file
 * Contains \Drupal\ckeditor\CKEditorPluginConfigurableInterface.
 */

namespace Drupal\ckeditor;

use Drupal\editor\Plugin\Core\Entity\Editor;

/**
 * Defines an interface for configurable CKEditor plugins.
 *
 * This allows a CKEditor plugin to define a settings form. These settings can
 * then be automatically passed on to the corresponding CKEditor instance via
 * CKEditorPluginInterface::getConfig().
 *
 * @see CKEditorPluginInterface
 * @see CKEditorPluginButtonsInterface
 * @see CKEditorPluginContextualInterface
 */
interface CKEditorPluginConfigurableInterface extends CKEditorPluginInterface {

  /**
   * Returns a settings form to configure this CKEditor plugin.
   *
   * If the plugin's behavior depends on extensive options and/or external data,
   * then the implementing module can choose to provide a separate, global
   * configuration page rather than per-text-editor settings. In that case, this
   * form should provide a link to the separate settings page.
   *
   * @param array $form
   *   An empty form array to be populated with a configuration form, if any.
   * @param array $form_state
   *   The state of the entire filter administration form.
   * @param \Drupal\editor\Plugin\Core\Entity\Editor $editor
   *   A configured text editor object.
   *
   * @return array|FALSE
   *   A render array for the settings form, or FALSE if there is none.
   */
  function settingsForm(array $form, array &$form_state, Editor $editor);

}
