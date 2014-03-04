<?php

/**
 * @file
 * Contains \Drupal\field_ui\Tests\ManageDisplayTest.
 */

namespace Drupal\field_ui\Tests;

use Drupal\Core\Entity\EntityInterface;

/**
 * Tests the functionality of the 'Manage display' screens.
 */
class ManageDisplayTest extends FieldUiTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('search', 'field_test');

  public static function getInfo() {
    return array(
      'name' => 'Manage display',
      'description' => 'Test the Field UI "Manage display" and "Manage form display" screens.',
      'group' => 'Field UI',
    );
  }

  /**
   * Tests formatter settings.
   */
  function testFormatterUI() {
    $manage_fields = 'admin/structure/types/manage/' . $this->type;
    $manage_display = $manage_fields . '/display';

    // Create a field, and a node with some data for the field.
    $edit = array(
      'fields[_add_new_field][label]' => 'Test field',
      'fields[_add_new_field][field_name]' => 'test',
    );
    $this->fieldUIAddNewField($manage_fields, $edit);

    // Clear the test-side cache and get the saved field instance.
    $display = entity_get_display('node', $this->type, 'default');
    $display_options = $display->getComponent('field_test');
    $format = $display_options['type'];
    $default_settings = \Drupal::service('plugin.manager.field.formatter')->getDefaultSettings($format);
    $setting_name = key($default_settings);
    $setting_value = $display_options['settings'][$setting_name];

    // Display the "Manage display" screen and check that the expected formatter is
    // selected.
    $this->drupalGet($manage_display);
    $this->assertFieldByName('fields[field_test][type]', $format, 'The expected formatter is selected.');
    $this->assertText("$setting_name: $setting_value", 'The expected summary is displayed.');

    // Change the formatter and check that the summary is updated.
    $edit = array('fields[field_test][type]' => 'field_test_multiple', 'refresh_rows' => 'field_test');
    $this->drupalPostAJAX(NULL, $edit, array('op' => t('Refresh')));
    $format = 'field_test_multiple';
    $default_settings = \Drupal::service('plugin.manager.field.formatter')->getDefaultSettings($format);
    $setting_name = key($default_settings);
    $setting_value = $default_settings[$setting_name];
    $this->assertFieldByName('fields[field_test][type]', $format, 'The expected formatter is selected.');
    $this->assertText("$setting_name: $setting_value", 'The expected summary is displayed.');

    // Submit the form and check that the display is updated.
    $this->drupalPost(NULL, array(), t('Save'));
    $display = entity_get_display('node', $this->type, 'default');
    $display_options = $display->getComponent('field_test');
    $current_format = $display_options['type'];
    $current_setting_value = $display_options['settings'][$setting_name];
    $this->assertEqual($current_format, $format, 'The formatter was updated.');
    $this->assertEqual($current_setting_value, $setting_value, 'The setting was updated.');

    // Assert that hook_field_formatter_settings_summary_alter() is called.
    $this->assertText('field_test_field_formatter_settings_summary_alter');

    // Click on the formatter settings button to open the formatter settings
    // form.
    $this->drupalPostAJAX(NULL, array(), "field_test_settings_edit");

    // Assert that the field added in
    // field_test_field_formatter_settings_form_alter() is present.
    $fieldname = 'fields[field_test][settings_edit_form][settings][field_test_formatter_settings_form_alter]';
    $this->assertField($fieldname, 'The field added in hook_field_formatter_settings_form_alter() is present on the settings form.');
    $edit = array($fieldname => 'foo');
    $this->drupalPostAJAX(NULL, $edit, "field_test_plugin_settings_update");

    // Confirm that the settings are updated on the settings form.
    $this->drupalPostAJAX(NULL, array(), "field_test_settings_edit");
    $this->assertFieldByName($fieldname, 'foo');
  }

  /**
   * Tests widget settings.
   */
  public function testWidgetUI() {
    $manage_fields = 'admin/structure/types/manage/' . $this->type;
    $manage_display = $manage_fields . '/form-display';

    // Create a field, and a node with some data for the field.
    $edit = array(
      'fields[_add_new_field][label]' => 'Test field',
      'fields[_add_new_field][field_name]' => 'test',
    );
    $this->fieldUIAddNewField($manage_fields, $edit);

    // Clear the test-side cache and get the saved field instance.
    $display = entity_get_form_display('node', $this->type, 'default');
    $display_options = $display->getComponent('field_test');
    $widget_type = $display_options['type'];
    $default_settings = \Drupal::service('plugin.manager.field.widget')->getDefaultSettings($widget_type);
    $setting_name = key($default_settings);
    $setting_value = $display_options['settings'][$setting_name];

    // Display the "Manage form display" screen and check that the expected
    // widget is selected.
    $this->drupalGet($manage_display);
    $this->assertFieldByName('fields[field_test][type]', $widget_type, 'The expected widget is selected.');
    $this->assertText("$setting_name: $setting_value", 'The expected summary is displayed.');

    // Change the widget and check that the summary is updated.
    $edit = array('fields[field_test][type]' => 'test_field_widget_multiple', 'refresh_rows' => 'field_test');
    $this->drupalPostAJAX(NULL, $edit, array('op' => t('Refresh')));
    $widget_type = 'test_field_widget_multiple';
    $default_settings = \Drupal::service('plugin.manager.field.widget')->getDefaultSettings($widget_type);
    $setting_name = key($default_settings);
    $setting_value = $default_settings[$setting_name];
    $this->assertFieldByName('fields[field_test][type]', $widget_type, 'The expected widget is selected.');
    $this->assertText("$setting_name: $setting_value", 'The expected summary is displayed.');

    // Submit the form and check that the display is updated.
    $this->drupalPost(NULL, array(), t('Save'));
    $display = entity_get_form_display('node', $this->type, 'default');
    $display_options = $display->getComponent('field_test');
    $current_widget = $display_options['type'];
    $current_setting_value = $display_options['settings'][$setting_name];
    $this->assertEqual($current_widget, $widget_type, 'The widget was updated.');
    $this->assertEqual($current_setting_value, $setting_value, 'The setting was updated.');

    // Assert that hook_field_widget_settings_summary_alter() is called.
    $this->assertText('field_test_field_widget_settings_summary_alter');

    // Click on the widget settings button to open the widget settings form.
    $this->drupalPostAJAX(NULL, array(), "field_test_settings_edit");

    // Assert that the field added in
    // field_test_field_widget_settings_form_alter() is present.
    $fieldname = 'fields[field_test][settings_edit_form][settings][field_test_widget_settings_form_alter]';
    $this->assertField($fieldname, 'The field added in hook_field_widget_settings_form_alter() is present on the settings form.');
    $edit = array($fieldname => 'foo');
    $this->drupalPostAJAX(NULL, $edit, "field_test_plugin_settings_update");

    // Confirm that the settings are updated on the settings form.
    $this->drupalPostAJAX(NULL, array(), "field_test_settings_edit");
    $this->assertFieldByName($fieldname, 'foo');
  }

  /**
   * Tests switching view modes to use custom or 'default' settings'.
   */
  function testViewModeCustom() {
    // Create a field, and a node with some data for the field.
    $edit = array(
      'fields[_add_new_field][label]' => 'Test field',
      'fields[_add_new_field][field_name]' => 'test',
    );
    $this->fieldUIAddNewField('admin/structure/types/manage/' . $this->type, $edit);
    // For this test, use a formatter setting value that is an integer unlikely
    // to appear in a rendered node other than as part of the field being tested
    // (for example, unlikely to be part of the "Submitted by ... on ..." line).
    $value = 12345;
    $settings = array(
      'type' => $this->type,
      'field_test' => array(array('value' => $value)),
    );
    $node = $this->drupalCreateNode($settings);

    // Gather expected output values with the various formatters.
    $formatters = \Drupal::service('plugin.manager.field.formatter')->getDefinitions();
    $output = array(
      'field_test_default' => $formatters['field_test_default']['settings']['test_formatter_setting'] . '|' . $value,
      'field_test_with_prepare_view' => $formatters['field_test_with_prepare_view']['settings']['test_formatter_setting_additional'] . '|' . $value. '|' . ($value + 1),
    );

    // Check that the field is displayed with the default formatter in 'rss'
    // mode (uses 'default'), and hidden in 'teaser' mode (uses custom settings).
    $this->assertNodeViewText($node, 'rss', $output['field_test_default'], "The field is displayed as expected in view modes that use 'default' settings.");
    $this->assertNodeViewNoText($node, 'teaser', $value, "The field is hidden in view modes that use custom settings.");

    // Change fomatter for 'default' mode, check that the field is displayed
    // accordingly in 'rss' mode.
    $edit = array(
      'fields[field_test][type]' => 'field_test_with_prepare_view',
    );
    $this->drupalPost('admin/structure/types/manage/' . $this->type . '/display', $edit, t('Save'));
    $this->assertNodeViewText($node, 'rss', $output['field_test_with_prepare_view'], "The field is displayed as expected in view modes that use 'default' settings.");

    // Specialize the 'rss' mode, check that the field is displayed the same.
    $edit = array(
      "display_modes_custom[rss]" => TRUE,
    );
    $this->drupalPost('admin/structure/types/manage/' . $this->type . '/display', $edit, t('Save'));
    $this->assertNodeViewText($node, 'rss', $output['field_test_with_prepare_view'], "The field is displayed as expected in newly specialized 'rss' mode.");

    // Set the field to 'hidden' in the view mode, check that the field is
    // hidden.
    $edit = array(
      'fields[field_test][type]' => 'hidden',
    );
    $this->drupalPost('admin/structure/types/manage/' . $this->type . '/display/rss', $edit, t('Save'));
    $this->assertNodeViewNoText($node, 'rss', $value, "The field is hidden in 'rss' mode.");

    // Set the view mode back to 'default', check that the field is displayed
    // accordingly.
    $edit = array(
      "display_modes_custom[rss]" => FALSE,
    );
    $this->drupalPost('admin/structure/types/manage/' . $this->type . '/display', $edit, t('Save'));
    $this->assertNodeViewText($node, 'rss', $output['field_test_with_prepare_view'], "The field is displayed as expected when 'rss' mode is set back to 'default' settings.");

    // Specialize the view mode again.
    $edit = array(
      "display_modes_custom[rss]" => TRUE,
    );
    $this->drupalPost('admin/structure/types/manage/' . $this->type . '/display', $edit, t('Save'));
    // Check that the previous settings for the view mode have been kept.
    $this->assertNodeViewNoText($node, 'rss', $value, "The previous settings are kept when 'rss' mode is specialized again.");
  }

  /**
   * Tests that field instances with no explicit display settings do not break.
   */
  function testNonInitializedFields() {
    // Create a test field.
    $edit = array(
      'fields[_add_new_field][label]' => 'Test',
      'fields[_add_new_field][field_name]' => 'test',
    );
    $this->fieldUIAddNewField('admin/structure/types/manage/' . $this->type, $edit);

    // Check that no settings have been set for the 'teaser' mode.
    $instance = field_info_instance('node', 'field_test', $this->type);
    $this->assertFalse(isset($instance['display']['teaser']));

    // Check that the field appears as 'hidden' on the 'Manage display' page
    // for the 'teaser' mode.
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/display/teaser');
    $this->assertFieldByName('fields[field_test][type]', 'hidden', 'The field is displayed as \'hidden \'.');
  }

  /**
   * Tests hiding the view modes fieldset when there's only one available.
   */
  function testSingleViewMode() {
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary . '/display');
    $this->assertNoText('Use custom display settings for the following view modes', 'Custom display settings fieldset found.');

    // This may not trigger a notice when 'view_modes_custom' isn't available.
    $this->drupalPost('admin/structure/taxonomy/manage/' . $this->vocabulary . '/display', array(), t('Save'));
  }

  /**
   * Tests that a message is shown when there are no fields.
   */
  function testNoFieldsDisplayOverview() {
    // Create a fresh content type without any fields.
    $this->drupalCreateContentType(array('type' => 'no_fields', 'name' => 'No fields'));

    // Remove the 'body' field.
    field_info_instance('node', 'body', 'no_fields')->delete();

    $this->drupalGet('admin/structure/types/manage/no_fields/display');
    $this->assertRaw(t('There are no fields yet added. You can add new fields on the <a href="@link">Manage fields</a> page.', array('@link' => url('admin/structure/types/manage/no_fields/fields'))));
  }

  /**
   * Asserts that a string is found in the rendered node in a view mode.
   *
   * @param EntityInterface $node
   *   The node.
   * @param $view_mode
   *   The view mode in which the node should be displayed.
   * @param $text
   *   Plain text to look for.
   * @param $message
   *   Message to display.
   *
   * @return
   *   TRUE on pass, FALSE on fail.
   */
  function assertNodeViewText(EntityInterface $node, $view_mode, $text, $message) {
    return $this->assertNodeViewTextHelper($node, $view_mode, $text, $message, FALSE);
  }

  /**
   * Asserts that a string is not found in the rendered node in a view mode.
   *
   * @param EntityInterface $node
   *   The node.
   * @param $view_mode
   *   The view mode in which the node should be displayed.
   * @param $text
   *   Plain text to look for.
   * @param $message
   *   Message to display.
   * @return
   *   TRUE on pass, FALSE on fail.
   */
  function assertNodeViewNoText(EntityInterface $node, $view_mode, $text, $message) {
    return $this->assertNodeViewTextHelper($node, $view_mode, $text, $message, TRUE);
  }

  /**
   * Asserts that a string is (not) found in the rendered nodein a view mode.
   *
   * This helper function is used by assertNodeViewText() and
   * assertNodeViewNoText().
   *
   * @param EntityInterface $node
   *   The node.
   * @param $view_mode
   *   The view mode in which the node should be displayed.
   * @param $text
   *   Plain text to look for.
   * @param $message
   *   Message to display.
   * @param $not_exists
   *   TRUE if this text should not exist, FALSE if it should.
   *
   * @return
   *   TRUE on pass, FALSE on fail.
   */
  function assertNodeViewTextHelper(EntityInterface $node, $view_mode, $text, $message, $not_exists) {
    // Make sure caches on the tester side are refreshed after changes
    // submitted on the tested side.
    field_info_cache_clear();

    // Save current content so that we can restore it when we're done.
    $old_content = $this->drupalGetContent();

    // Render a cloned node, so that we do not alter the original.
    $clone = clone $node;
    $element = node_view($clone, $view_mode);
    $output = drupal_render($element);
    $this->verbose(t('Rendered node - view mode: @view_mode', array('@view_mode' => $view_mode)) . '<hr />'. $output);

    // Assign content so that WebTestBase functions can be used.
    $this->drupalSetContent($output);
    $method = ($not_exists ? 'assertNoText' : 'assertText');
    $return = $this->{$method}((string) $text, $message);

    // Restore previous content.
    $this->drupalSetContent($old_content);

    return $return;
  }
}
