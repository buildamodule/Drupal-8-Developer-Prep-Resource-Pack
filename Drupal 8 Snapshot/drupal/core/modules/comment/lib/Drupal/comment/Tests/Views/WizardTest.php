<?php

/**
 * @file
 * Contains \Drupal\comment\Tests\Views\WizardTest.
 */

namespace Drupal\comment\Tests\Views;

use Drupal\views\Tests\Wizard\WizardTestBase;

/**
 * Tests the comment module integration into the wizard.
 *
 * @see Drupal\comment\Plugin\views\wizard\Comment
 */
class WizardTest extends WizardTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('comment');


  public static function getInfo() {
    return array(
      'name' => 'Comment: Wizard',
      'description' => 'Tests the comment module integration into the wizard.',
      'group' => 'Views Wizard',
    );
  }

  /**
   * Tests adding a view of comments.
   */
  public function testCommentWizard() {
    $view = array();
    $view['label'] = $this->randomName(16);
    $view['id'] = strtolower($this->randomName(16));
    $view['show[wizard_key]'] = 'comment';
    $view['page[create]'] = TRUE;
    $view['page[path]'] = $this->randomName(16);

    // Just triggering the saving should automatically choose a proper row
    // plugin.
    $this->drupalPost('admin/structure/views/add', $view, t('Save and edit'));
    $this->assertUrl('admin/structure/views/view/' . $view['id'], array(), 'Make sure the view saving was successful and the browser got redirected to the edit page.');

    // If we update the type first we should get a selection of comment valid
    // row plugins as the select field.

    $this->drupalGet('admin/structure/views/add');
    $this->drupalPost('admin/structure/views/add', $view, t('Update "of type" choice'));

    // Check for available options of the row plugin.
    $xpath = $this->constructFieldXpath('name', 'page[style][row_plugin]');
    $fields = $this->xpath($xpath);
    $options = array();
    foreach ($fields as $field) {
      $items = $this->getAllOptions($field);
      foreach ($items as $item) {
        $options[] = $item->attributes()->value;
      }
    }
    $expected_options = array('comment', 'fields');
    $this->assertEqual($options, $expected_options);

    $view['id'] = strtolower($this->randomName(16));
    $this->drupalPost(NULL, $view, t('Save and edit'));
    $this->assertUrl('admin/structure/views/view/' . $view['id'], array(), 'Make sure the view saving was successful and the browser got redirected to the edit page.');

    $view = views_get_view($view['id']);
    $view->initHandlers();
    $row = $view->display_handler->getOption('row');
    $this->assertEqual($row['type'], 'entity:comment');

    // Check for the default filters.
    $this->assertEqual($view->filter['status']->table, 'comment');
    $this->assertEqual($view->filter['status']->field, 'status');
    $this->assertTrue($view->filter['status']->value);
    $this->assertEqual($view->filter['status_node']->table, 'node_field_data');
    $this->assertEqual($view->filter['status_node']->field, 'status');
    $this->assertTrue($view->filter['status_node']->value);

    // Check for the default fields.
    $this->assertEqual($view->field['subject']->table, 'comment');
    $this->assertEqual($view->field['subject']->field, 'subject');
  }

}
