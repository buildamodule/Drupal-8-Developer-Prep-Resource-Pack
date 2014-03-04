<?php

/**
 * @file
 * Contains \Drupal\entity_reference\Tests\EntityReferenceAutoCreateTest.
 */

namespace Drupal\entity_reference\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the Entity Reference auto-creation feature.
 */
class EntityReferenceAutoCreateTest extends WebTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Entity Reference auto-create',
      'description' => 'Tests creating new entity (e.g. taxonomy-term) from an autocomplete widget.',
      'group' => 'Entity Reference',
    );
  }

  public static $modules = array('entity_reference', 'node');

  function setUp() {
    parent::setUp();

    // Create "referencing" and "referenced" node types.
    $referencing = $this->drupalCreateContentType();
    $this->referencing_type = $referencing->type;

    $referenced = $this->drupalCreateContentType();
    $this->referenced_type = $referenced->type;

    entity_create('field_entity', array(
      'translatable' => FALSE,
      'entity_types' => array(),
      'settings' => array(
        'target_type' => 'node',
      ),
      'field_name' => 'test_field',
      'type' => 'entity_reference',
      'cardinality' => FIELD_CARDINALITY_UNLIMITED,
    ))->save();

    entity_create('field_instance', array(
      'label' => 'Entity reference field',
      'field_name' => 'test_field',
      'entity_type' => 'node',
      'bundle' => $referencing->type,
      'settings' => array(
        'handler' => 'default',
        'handler_settings' => array(
          // Reference a single vocabulary.
          'target_bundles' => array(
            $referenced->type,
          ),
          // Enable auto-create.
          'auto_create' => TRUE,
        ),
      ),
    ))->save();

    entity_get_display('node', $referencing->type, 'default')
      ->setComponent('test_field')
      ->save();
    entity_get_form_display('node', $referencing->type, 'default')
      ->setComponent('test_field', array(
        'type' => 'entity_reference_autocomplete',
      ))
      ->save();
  }

  /**
   * Assert creation on a new entity.
   */
  public function testAutoCreate() {
    $user1 = $this->drupalCreateUser(array('access content', "create $this->referencing_type content"));
    $this->drupalLogin($user1);

    $new_title = $this->randomName();

    // Assert referenced node does not exist.
    $base_query = \Drupal::entityQuery('node');
    $base_query
      ->condition('type', $this->referenced_type)
      ->condition('title', $new_title);

    $query = clone $base_query;
    $result = $query->execute();
    $this->assertFalse($result, 'Referenced node does not exist yet.');

    $edit = array(
      'title' => $this->randomName(),
      'test_field[und][0][target_id]' => $new_title,
    );
    $this->drupalPost("node/add/$this->referencing_type", $edit, 'Save');

    // Assert referenced node was created.
    $query = clone $base_query;
    $result = $query->execute();
    $this->assertTrue($result, 'Referenced node was created.');
    $referenced_nid = key($result);
    $referenced_node = node_load($referenced_nid);

    // Assert the referenced node is associated with referencing node.
    $result = \Drupal::entityQuery('node')
      ->condition('type', $this->referencing_type)
      ->execute();

    $referencing_nid = key($result);
    $referencing_node = node_load($referencing_nid);
    $this->assertEqual($referenced_nid, $referencing_node->test_field[Language::LANGCODE_NOT_SPECIFIED][0]['target_id'], 'Newly created node is referenced from the referencing node.');

    // Now try to view the node and check that the referenced node is shown.
    $this->drupalGet('node/' . $referencing_node->id());
    $this->assertText($referencing_node->label(), 'Referencing node label found.');
    $this->assertText($referenced_node->label(), 'Referenced node label found.');
  }
}
