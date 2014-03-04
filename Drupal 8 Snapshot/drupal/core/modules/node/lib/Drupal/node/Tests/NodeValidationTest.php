<?php

/**
 * @file
 * Contains \Drupal\node\Tests\NodeValidationTest.
 */

namespace Drupal\node\Tests;

use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests node validation constraints.
 */
class NodeValidationTest extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'entity', 'field', 'text', 'field_sql_storage');

  public static function getInfo() {
    return array(
      'name' => 'Node Validation',
      'description' => 'Tests the node validation constraints.',
      'group' => 'Node',
    );
  }

  /**
   * Set the default field storage backend for fields created during tests.
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('node', 'node');
    $this->installSchema('node', 'node_field_data');
    $this->installSchema('node', 'node_field_revision');

    // Create a node type for testing.
    $type = entity_create('node_type', array('type' => 'page', 'name' => 'page'));
    $type->save();
  }

  /**
   * Tests the node validation constraints.
   */
  public function testValidation() {
    $node = entity_create('node', array('type' => 'page', 'title' => 'test'));
    $violations = $node->validate();
    $this->assertEqual(count($violations), 0, 'No violations when validating a default node.');

    $node->set('title', $this->randomString(256));
    $violations = $node->validate();
    $this->assertEqual(count($violations), 1, 'Violation found when title is too long.');
    $this->assertEqual($violations[0]->getPropertyPath(), 'title.0.value');
    $this->assertEqual($violations[0]->getMessage(), t('This value is too long. It should have %limit characters or less.', array('%limit' => 255)));

    $node->set('title', NULL);
    $violations = $node->validate();
    $this->assertEqual(count($violations), 1, 'Violation found when title is not set.');
    $this->assertEqual($violations[0]->getPropertyPath(), 'title');
    $this->assertEqual($violations[0]->getMessage(), t('This value should not be null.'));

    // Make the title valid again.
    $node->set('title', $this->randomString());
    // Save the node so that it gets an ID and a changed date.
    $node->save();
    // Set the changed date to something in the far past.
    $node->set('changed', 433918800);
    $violations = $node->validate();
    $this->assertEqual(count($violations), 1, 'Violation found when changed date is before the last changed date.');
    $this->assertEqual($violations[0]->getPropertyPath(), 'changed.0.value');
    $this->assertEqual($violations[0]->getMessage(), t('The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.'));
  }
}
