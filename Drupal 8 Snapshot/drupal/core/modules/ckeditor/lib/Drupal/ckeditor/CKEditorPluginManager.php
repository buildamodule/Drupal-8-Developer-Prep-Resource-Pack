<?php

/**
 * @file
 * Contains \Drupal\ckeditor\CKEditorPluginManager.
 */

namespace Drupal\ckeditor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\editor\Plugin\Core\Entity\Editor;

/**
 * CKEditor Plugin manager.
 */
class CKEditorPluginManager extends DefaultPluginManager {

  /**
   * Constructs a CKEditorPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, LanguageManager $language_manager, ModuleHandlerInterface $module_handler) {
    $annotation_namespaces = array('Drupal\ckeditor\Annotation' => $namespaces['Drupal\ckeditor']);
    parent::__construct('Plugin/CKEditorPlugin', $namespaces, $annotation_namespaces, 'Drupal\ckeditor\Annotation\CKEditorPlugin');
    $this->alterInfo($module_handler, 'ckeditor_plugin_info');
    $this->setCacheBackend($cache_backend, $language_manager, 'ckeditor_plugin');
  }

  /**
   * Retrieves enabled plugins' files, keyed by plugin ID.
   *
   * For CKEditor plugins that implement:
   *  - CKEditorPluginButtonsInterface, not CKEditorPluginContextualInterface,
   *     a plugin is enabled if at least one of its buttons is in the toolbar;
   *  - CKEditorPluginContextualInterface, not CKEditorPluginButtonsInterface,
   *     a plugin is enabled if its isEnabled() method returns TRUE
   *  - both of these interfaces, a plugin is enabled if either is the case.
   *
   * Internal plugins (those that are part of the bundled build of CKEditor) are
   * excluded by default, since they are loaded implicitly. If you need to know
   * even implicitly loaded (i.e. internal) plugins, then set the optional
   * second parameter.
   *
   * @param \Drupal\editor\Plugin\Core\Entity\Editor $editor
   *   A configured text editor object.
   * @param bool $include_internal_plugins
   *   Defaults to FALSE. When set to TRUE, plugins whose isInternal() method
   *   returns TRUE will also be included.
   * @return array
   *   A list of the enabled CKEditor plugins, with the plugin IDs as keys and
   *   the Drupal root-relative plugin files as values.
   *   For internal plugins, the value is NULL.
   */
  public function getEnabledPluginFiles(Editor $editor, $include_internal_plugins = FALSE) {
    $plugins = array_keys($this->getDefinitions());
    $toolbar_buttons = array_unique(NestedArray::mergeDeepArray($editor->settings['toolbar']['buttons']));
    $enabled_plugins = array();
    $additional_plugins = array();

    foreach ($plugins as $plugin_id) {
      $plugin = $this->createInstance($plugin_id);

      if (!$include_internal_plugins && $plugin->isInternal()) {
        continue;
      }

      $enabled = FALSE;
      // Enable this plugin if it provides a button that has been enabled.
      if ($plugin instanceof CKEditorPluginButtonsInterface) {
        $plugin_buttons = array_keys($plugin->getButtons());
        $enabled = (count(array_intersect($toolbar_buttons, $plugin_buttons)) > 0);
      }
      // Otherwise enable this plugin if it declares itself as enabled.
      if (!$enabled && $plugin instanceof CKEditorPluginContextualInterface) {
        $enabled = $plugin->isEnabled($editor);
      }

      if ($enabled) {
        $enabled_plugins[$plugin_id] = ($plugin->isInternal()) ? NULL : $plugin->getFile();
        // Check if this plugin has dependencies that also need to be enabled.
        $additional_plugins = array_merge($additional_plugins, array_diff($plugin->getDependencies($editor), $additional_plugins));
      }
    }

    // Add the list of dependent plugins.
    foreach ($additional_plugins as $plugin_id) {
      $plugin = $this->createInstance($plugin_id);
      $enabled_plugins[$plugin_id] = ($plugin->isInternal()) ? NULL : $plugin->getFile();
    }

    // Always return plugins in the same order.
    asort($enabled_plugins);

    return $enabled_plugins;
  }

  /**
   * Retrieves all available CKEditor buttons, keyed by plugin ID.
   *
   * @return array
   *   All availble CKEditor buttons, with plugin IDs as keys and button
   *   metadata (as implemented by getButtons()) as values.
   *
   * @see CKEditorPluginButtonsInterface::getButtons()
   */
  public function getButtons() {
    $plugins = array_keys($this->getDefinitions());
    $buttons_plugins = array();

    foreach ($plugins as $plugin_id) {
      $plugin = $this->createInstance($plugin_id);
      if ($plugin instanceof CKEditorPluginButtonsInterface) {
        $buttons_plugins[$plugin_id] = $plugin->getButtons();
      }
    }

    return $buttons_plugins;
  }

  /**
   * Injects the CKEditor plugins settings forms as a vertical tabs subform.
   *
   * @param array &$form
   *   A reference to an associative array containing the structure of the form.
   * @param array &$form_state
   *   A reference to a keyed array containing the current state of the form.
   * @param \Drupal\editor\Plugin\Core\Entity\Editor $editor
   *   A configured text editor object.
   */
  public function injectPluginSettingsForm(array &$form, array &$form_state, Editor $editor) {
    $definitions = $this->getDefinitions();

    foreach (array_keys($definitions) as $plugin_id) {
      $plugin = $this->createInstance($plugin_id);
      if ($plugin instanceof CKEditorPluginConfigurableInterface) {
        $plugin_settings_form = array();
        $form['plugins'][$plugin_id] = array(
          '#type' => 'details',
          '#title' => $definitions[$plugin_id]['label'],
          '#group' => 'editor][settings][plugin_settings',
        );
        $form['plugins'][$plugin_id] += $plugin->settingsForm($plugin_settings_form, $form_state, $editor);
      }
    }
  }
}
