<?php

/**
 * @file
 * Contains \Drupal\action\Tests\BulkFormTest.
 */

namespace Drupal\action\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the views bulk form test.
 *
 * @see \Drupal\action\Plugin\views\field\BulkForm
 */
class BulkFormTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('action_bulk_test');

  public static function getInfo() {
    return array(
      'name' => 'Bulk form',
      'description' => 'Tests the views bulk form test.',
      'group' => 'Action',
    );
  }

  /**
   * Tests the bulk form.
   */
  public function testBulkForm() {
    $nodes = array();
    for ($i = 0; $i < 10; $i++) {
      // Ensure nodes are sorted in the same order they are inserted in the
      // array.
      $timestamp = REQUEST_TIME - $i;
      $nodes[] = $this->drupalCreateNode(array(
        'sticky' => FALSE,
        'created' => $timestamp,
        'changed' => $timestamp,
      ));
    }

    $this->drupalGet('test_bulk_form');

    $this->assertFieldById('edit-action', NULL, 'The action select field appears.');

    // Make sure a checkbox appears on all rows.
    $edit = array();
    for ($i = 0; $i < 10; $i++) {
      $this->assertFieldById('edit-action-bulk-form-' . $i, NULL, format_string('The checkbox on row @row appears.', array('@row' => $i)));
      $edit["action_bulk_form[$i]"] = TRUE;
    }

    // Set all nodes to sticky and check that.
    $edit += array('action' => 'node_make_sticky_action');
    $this->drupalPost(NULL, $edit, t('Apply'));

    foreach ($nodes as $node) {
      $changed_node = node_load($node->id());
      $this->assertTrue($changed_node->sticky, format_string('Node @nid got marked as sticky.', array('@nid' => $node->id())));
    }

    $this->assertText('Make content sticky was applied to 10 items.');

    // Unpublish just one node.
    $node = node_load($nodes[0]->id());
    $this->assertTrue($node->status, 'The node is published.');

    $edit = array('action_bulk_form[0]' => TRUE, 'action' => 'node_unpublish_action');
    $this->drupalPost(NULL, $edit, t('Apply'));

    $this->assertText('Unpublish content was applied to 1 item.');

    // Load the node again.
    $node = node_load($node->id(), TRUE);
    $this->assertFalse($node->status, 'A single node has been unpublished.');

    // The second node should still be published.
    $node = node_load($nodes[1]->id(), TRUE);
    $this->assertTrue($node->status, 'An unchecked node is still published.');

    // Set up to include just the sticky actions.
    $view = views_get_view('test_bulk_form');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['fields']['action_bulk_form']['include_exclude'] = 'include';
    $display['display_options']['fields']['action_bulk_form']['selected_actions']['node_make_sticky_action'] = 'node_make_sticky_action';
    $display['display_options']['fields']['action_bulk_form']['selected_actions']['node_make_unsticky_action'] = 'node_make_unsticky_action';
    $view->save();

    $this->drupalGet('test_bulk_form');
    $options = $this->xpath('//select[@id=:id]/option', array(':id' => 'edit-action'));
    $this->assertEqual(count($options), 2);
    $this->assertOption('edit-action', 'node_make_sticky_action');
    $this->assertOption('edit-action', 'node_make_unsticky_action');

    // Set up to exclude the sticky actions.
    $view = views_get_view('test_bulk_form');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['fields']['action_bulk_form']['include_exclude'] = 'exclude';
    $view->save();

    $this->drupalGet('test_bulk_form');
    $this->assertNoOption('edit-action', 'node_make_sticky_action');
    $this->assertNoOption('edit-action', 'node_make_unsticky_action');
  }

}
