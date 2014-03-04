<?php

/**
 * @file
 * Contains \Drupal\entity_reference\Tests\Views\SelectionTest.
 */

namespace Drupal\entity_reference\Tests\Views;

use Drupal\simpletest\WebTestBase;

/**
 * Tests entity reference selection handler.
 */
class SelectionTest extends WebTestBase {

  public static $modules = array('views', 'entity_reference', 'entity_reference_test');

  public static function getInfo() {
    return array(
      'name' => 'Entity Reference: Selection handler',
      'description' => 'Tests entity reference selection handler provided by Views.',
      'group' => 'Views module integration',
    );
  }

  /**
   * Tests the selection handler.
   */
  public function testSelectionHandler() {
    // Create nodes.
    $type = $this->drupalCreateContentType()->type;
    $node1 = $this->drupalCreateNode(array('type' => $type));
    $node2 = $this->drupalCreateNode(array('type' => $type));
    $node3 = $this->drupalCreateNode();

    $nodes = array();
    foreach (array($node1, $node2, $node3) as $node) {
      $nodes[$node->type][$node->id()] = $node->label();
    }

    // Create a field and instance.
    $field = entity_create('field_entity', array(
      'translatable' => FALSE,
      'entity_types' => array(),
      'settings' => array(
        'target_type' => 'node',
      ),
      'field_name' => 'test_field',
      'type' => 'entity_reference',
      'cardinality' => '1',
    ));
    $field->save();
    $instance = entity_create('field_instance', array(
      'field_name' => 'test_field',
      'entity_type' => 'test_entity',
      'bundle' => 'test_bundle',
      'settings' => array(
        'handler' => 'views',
        'handler_settings' => array(
          'target_bundles' => array(),
          'view' => array(
            'view_name' => 'test_entity_reference',
            'display_name' => 'entity_reference_1',
            'arguments' => array(),
          ),
        ),
      ),
    ));
    $instance->save();

    // Get values from selection handler.
    $handler = $this->container->get('plugin.manager.entity_reference.selection')->getSelectionHandler($instance);
    $result = $handler->getReferenceableEntities();

    $success = FALSE;
    foreach ($result as $node_type => $values) {
      foreach ($values as $nid => $label) {
        if (!$success = $nodes[$node_type][$nid] == trim(strip_tags($label))) {
          // There was some error, so break.
          break;
        }
      }
    }

    $this->assertTrue($success, 'Views selection handler returned expected values.');
  }
}
