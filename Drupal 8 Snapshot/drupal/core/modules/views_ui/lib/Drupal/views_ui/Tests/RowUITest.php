<?php

/**
 * @file
 * Contains \Drupal\views_ui\Tests\RowUITest.
 */

namespace Drupal\views_ui\Tests;

/**
 * Tests the UI of row plugins.
 *
 * @see Drupal\views_test_data\Plugin\views\row\RowTest.
 */
class RowUITest extends UITestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_view');

  public static function getInfo() {
    return array(
      'name' => 'Row: UI',
      'description' => 'Tests the UI of row plugins.',
      'group' => 'Views UI',
    );
  }

  /**
   * Tests changing the row plugin and changing some options of a row.
   */
  public function testRowUI() {
    $view_name = 'test_view';
    $view_edit_url = "admin/structure/views/view/$view_name/edit";

    $row_plugin_url = "admin/structure/views/nojs/display/$view_name/default/row";
    $row_options_url = "admin/structure/views/nojs/display/$view_name/default/row_options";

    $this->drupalGet($row_plugin_url);
    $this->assertFieldByName('row', 'fields', 'The default row plugin selected in the UI should be fields.');

    $edit = array(
      'row' => 'test_row'
    );
    $this->drupalPost(NULL, $edit, t('Apply'));
    $this->assertFieldByName('row_options[test_option]', NULL, 'Make sure the custom settings form from the test plugin appears.');
    $random_name = $this->randomName();
    $edit = array(
      'row_options[test_option]' => $random_name
    );
    $this->drupalPost(NULL, $edit, t('Apply'));
    $this->drupalGet($row_options_url);
    $this->assertFieldByName('row_options[test_option]', $random_name, 'Make sure the custom settings form field has the expected value stored.');

    $this->drupalPost($view_edit_url, array(), t('Save'));
    $this->assertLink(t('Test row plugin'), 0, 'Make sure the test row plugin is shown in the UI');

    $view = views_get_view($view_name);
    $view->initDisplay();
    $row = $view->display_handler->getOption('row');
    $this->assertEqual($row['type'], 'test_row', 'Make sure that the test_row got saved as used row plugin.');
    $this->assertEqual($row['options']['test_option'], $random_name, 'Make sure that the custom settings field got saved as expected.');
  }

}
