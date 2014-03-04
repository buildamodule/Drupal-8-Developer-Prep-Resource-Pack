<?php

/**
 * @file
 * Documentation for Text Editor API.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Performs alterations on text editor definitions.
 *
 * @param array $editors
 *   An array of metadata of text editors, as collected by the plugin annotation
 *   discovery mechanism.
 *
 * @see \Drupal\editor\Plugin\EditorBase
 */
function hook_editor_info_alter(array &$editors) {
  $editors['some_other_editor']['label'] = t('A different name');
  $editors['some_other_editor']['library']['module'] = 'myeditoroverride';
}

/**
 * Provides defaults for editor instances.
 *
 * Modules that extend the list of settings for a particular text editor library
 * should specify defaults for those settings using this hook. These settings
 * will be used for any new editors, as well as merged into any existing editor
 * configuration that has not yet been provided with a specific value for a
 * setting (as may happen when a module providing a new setting is enabled after
 * the text editor has been configured).
 *
 * Note that only the top-level of this array is merged into the defaults. If
 * multiple modules provide nested settings with the same top-level key, only
 * the first will be used. Modules should avoid deep nesting of settings to
 * avoid defaults being undefined.
 *
 * The return value of this hook is not cached. If retrieving defaults in a
 * complex manner, the implementing module should provide its own caching inside
 * the hook.
 *
 * @param $editor
 *   A string indicating the name of the editor library whose default settings
 *   are being provided.
 *
 * @return array
 *   An array of default settings that will be merged into the editor defaults.
 */
function hook_editor_default_settings($editor) {
  return array(
    'mymodule_new_setting1' => TRUE,
    'mymodule_new_setting2' => array(
      'foo' => 'baz',
      'bar' => 'qux',
    ),
  );
}

/**
 * Modifies default settings for editor instances.
 *
 * Modules that extend the behavior of other modules may use this hook to change
 * the default settings provided to new and existing editors. This hook should
 * be used when changing an existing setting to a new value. To add a new
 * default setting, hook_editor_default_settings() should be used.
 *
 * The return value of this hook is not cached. If retrieving defaults in a
 * complex manner, the implementing module should provide its own caching inside
 * the hook.
 *
 * @param $default_settings
 *   The array of default settings which may be modified, passed by reference.
 * @param $editor
 *   A string indicating the name of the editor library whose default settings
 *   are being provided.
 *
 * @return array
 *   An array of default settings that will be merged into the editor defaults.
 *
 * @see hook_editor_default_settings()
 */
function hook_editor_default_settings_alter(&$default_settings, $editor) {
  $default_settings['toolbar'] = array('Bold', 'Italics', 'Underline');
}

/**
 * Modifies JavaScript settings that are added for text editors.
 *
 * @param array $settings
 *   All the settings that will be added to the page via drupal_add_js() for
 *   the text formats to which a user has access.
 * @param array $formats
 *   The list of format objects for which settings are being added.
 */
function hook_editor_js_settings_alter(array &$settings, array $formats) {
  if (isset($formats['basic_html'])) {
    $settings['basic_html']['editor'][] = 'MyDifferentEditor';
    $settings['basic_html']['editorSettings']['buttons'] = array('strong', 'italic', 'underline');
  }
}

/**
 * @} End of "addtogroup hooks".
 */
