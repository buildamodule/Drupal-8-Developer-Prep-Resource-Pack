<?php

/**
 * @file
 * Definition of Drupal\field\Tests\BulkDeleteTest.
 */

namespace Drupal\field\Tests;

use Drupal\field\Plugin\Core\Entity\FieldInstance;
use Drupal\Core\Entity\EntityInterface;

use Drupal\Core\Language\Language;

/**
 * Unit test class for field bulk delete and batch purge functionality.
 */
class BulkDeleteTest extends FieldUnitTestBase {

  /**
   * The fields to use in this test.
   *
   * @var array
   */
  protected $fields;

  /**
   * The entities to use in this test.
   *
   * @var array
   */
  protected $entities;

  /**
   * The entities to use in this test, keyed by bundle.
   *
   * @var array
   */
  protected $entities_by_bundles;

  /**
   * The bundles for the entities used in this test.
   *
   * @var array
   */
  protected $bundles;

  /**
   * The entity type to be used in the test classes.
   *
   * @var array
   */
  protected $entity_type = 'test_entity';

  public static function getInfo() {
    return array(
      'name' => 'Field bulk delete tests',
      'description' => 'Bulk delete fields and instances, and clean up afterwards.',
      'group' => 'Field API',
    );
  }

  /**
   * Tests that the expected hooks have been invoked on the expected entities.
   *
   * @param $expected_hooks
   *   An array keyed by hook name, with one entry per expected invocation.
   *   Each entry is the value of the "$entity" parameter the hook is expected
   *   to have been passed.
   * @param $actual_hooks
   *   The array of actual hook invocations recorded by field_test_memorize().
   */
  function checkHooksInvocations($expected_hooks, $actual_hooks) {
    foreach ($expected_hooks as $hook => $invocations) {
      $actual_invocations = $actual_hooks[$hook];

      // Check that the number of invocations is correct.
      $this->assertEqual(count($actual_invocations), count($invocations), "$hook() was called the expected number of times.");

      // Check that the hook was called for each expected argument.
      foreach ($invocations as $argument) {
        $found = FALSE;
        foreach ($actual_invocations as $actual_arguments) {
          // The argument we are looking for is either an array of entities as
          // the second argument or a single entity object as the first.
          if ($argument instanceof EntityInterface && $actual_arguments[0]->id() == $argument->id()) {
            $found = TRUE;
            break;
          }
          // In case of an array, compare the array size and make sure it
          // contains the same elements.
          elseif (is_array($argument) && count($actual_arguments[1]) == count($argument) && count(array_diff_key($actual_arguments[1], $argument)) == 0) {
            $found = TRUE;
            break;
          }
        }
        $this->assertTrue($found, "$hook() was called on expected argument");
      }
    }
  }

  function setUp() {
    parent::setUp();

    $this->fields = array();
    $this->entities = array();
    $this->entities_by_bundles = array();

    // Create two bundles.
    $this->bundles = array('bb_1' => 'bb_1', 'bb_2' => 'bb_2');
    foreach ($this->bundles as $name => $desc) {
      entity_test_create_bundle($name, $desc);
    }

    // Create two fields.
    $field = entity_create('field_entity', array(
      'field_name' => 'bf_1',
      'type' => 'test_field',
      'cardinality' => 1
    ));
    $field->save();
    $this->fields[] = $field;
    $field = entity_create('field_entity', array(
      'field_name' => 'bf_2',
      'type' => 'test_field',
      'cardinality' => 4
    ));
    $field->save();
    $this->fields[] = $field;

    // For each bundle, create an instance of each field, and 10
    // entities with values for each field.
    $this->entity_type = 'entity_test';
    foreach ($this->bundles as $bundle) {
      foreach ($this->fields as $field) {
        entity_create('field_instance', array(
          'field_name' => $field->id(),
          'entity_type' => $this->entity_type,
          'bundle' => $bundle,
        ))->save();
      }
      for ($i = 0; $i < 10; $i++) {
        $entity = entity_create($this->entity_type, array('type' => $bundle));
        foreach ($this->fields as $field) {
          $entity->{$field['field_name']}->setValue($this->_generateTestFieldValues($field->cardinality));
        }
        $entity->save();
      }
    }
    $this->entities = entity_load_multiple($this->entity_type);
    foreach ($this->entities as $entity) {
      // Also keep track of the entities per bundle.
      $this->entities_by_bundles[$entity->bundle()][$entity->id()] = $entity;
    }
  }

  /**
   * Verify that deleting an instance leaves the field data items in
   * the database and that the appropriate Field API functions can
   * operate on the deleted data and instance.
   *
   * This tests how EntityFieldQuery interacts with field instance deletion and
   * could be moved to FieldCrudTestCase, but depends on this class's setUp().
   */
  function testDeleteFieldInstance() {
    $bundle = reset($this->bundles);
    $field = reset($this->fields);
    $field_name = $field->id();
    $factory = \Drupal::service('entity.query');

    // There are 10 entities of this bundle.
    $found = $factory->get('entity_test')
      ->condition('type', $bundle)
      ->execute();
    $this->assertEqual(count($found), 10, 'Correct number of entities found before deleting');

    // Delete the instance.
    $instance = field_info_instance($this->entity_type, $field->id(), $bundle);
    $instance->delete();

    // The instance still exists, deleted.
    $instances = field_read_instances(array('field_id' => $field->uuid, 'deleted' => TRUE), array('include_deleted' => TRUE, 'include_inactive' => TRUE));
    $this->assertEqual(count($instances), 1, 'There is one deleted instance');
    $instance = $instances[0];
    $this->assertEqual($instance['bundle'], $bundle, 'The deleted instance is for the correct bundle');

    // There are 0 entities of this bundle with non-deleted data.
    $found = $factory->get('entity_test')
      ->condition('type', $bundle)
      ->condition("$field_name.deleted", 0)
      ->execute();
    $this->assertFalse($found, 'No entities found after deleting');

    // There are 10 entities of this bundle when deleted fields are allowed, and
    // their values are correct.
    $found = $factory->get('entity_test')
      ->condition('type', $bundle)
      ->condition("$field_name.deleted", 1)
      ->sort('id')
      ->execute();
    $ids = (object) array(
      'entity_type' => 'entity_test',
      'bundle' => $bundle,
    );
    $entities = array();
    foreach ($found as $entity_id) {
      $ids->entity_id = $entity_id;
      $entities[$entity_id] = _field_create_entity_from_ids($ids);
    }
    field_attach_load($this->entity_type, $entities, FIELD_LOAD_CURRENT, array('instance' => $instance));
    $this->assertEqual(count($found), 10, 'Correct number of entities found after deleting');
    foreach ($entities as $id => $entity) {
      $this->assertEqual($this->entities[$id]->{$field->id()}->value, $entity->{$field->id()}[Language::LANGCODE_NOT_SPECIFIED][0]['value'], "Entity $id with deleted data loaded correctly");
    }
  }

  /**
   * Verify that field data items and instances are purged when an
   * instance is deleted.
   */
  function testPurgeInstance() {
    // Start recording hook invocations.
    field_test_memorize();

    $bundle = reset($this->bundles);
    $field = reset($this->fields);

    // Delete the instance.
    $instance = field_info_instance($this->entity_type, $field->id(), $bundle);
    $instance->delete();

    // No field hooks were called.
    $mem = field_test_memorize();
    $this->assertEqual(count($mem), 0, 'No field hooks were called');

    $batch_size = 2;
    for ($count = 8; $count >= 0; $count -= $batch_size) {
      // Purge two entities.
      field_purge_batch($batch_size);

      // There are $count deleted entities left.
      $found = \Drupal::entityQuery('entity_test')
        ->condition('type', $bundle)
        ->condition($field->id() . '.deleted', 1)
        ->execute();
      $this->assertEqual(count($found), $count, 'Correct number of entities found after purging 2');
    }

    // Check hooks invocations.
    // hook_field_load() and hook_field_delete() should have been called once
    // for each entity in the bundle.
    $actual_hooks = field_test_memorize();
    $hooks = array();
    $entities = $this->entities_by_bundles[$bundle];
    foreach ($entities as $id => $entity) {
      $hooks['field_test_field_load'][] = array($id => $entity);
      $hooks['field_test_field_delete'][] = $entity;
    }
    $this->checkHooksInvocations($hooks, $actual_hooks);

    // The instance still exists, deleted.
    $instances = field_read_instances(array('field_id' => $field->uuid, 'deleted' => TRUE), array('include_deleted' => TRUE, 'include_inactive' => TRUE));
    $this->assertEqual(count($instances), 1, 'There is one deleted instance');

    // Purge the instance.
    field_purge_batch($batch_size);

    // The instance is gone.
    $instances = field_read_instances(array('field_id' => $field->uuid, 'deleted' => TRUE), array('include_deleted' => TRUE, 'include_inactive' => TRUE));
    $this->assertEqual(count($instances), 0, 'The instance is gone');

    // The field still exists, not deleted, because it has a second instance.
    $fields = field_read_fields(array('uuid' => $field->uuid), array('include_deleted' => TRUE, 'include_inactive' => TRUE));
    $this->assertTrue(isset($fields[$field->uuid]), 'The field exists and is not deleted');
  }

  /**
   * Verify that fields are preserved and purged correctly as multiple
   * instances are deleted and purged.
   */
  function testPurgeField() {
    // Start recording hook invocations.
    field_test_memorize();

    $field = reset($this->fields);

    // Delete the first instance.
    $bundle = reset($this->bundles);
    $instance = field_info_instance($this->entity_type, $field->id(), $bundle);
    $instance->delete();

    // Assert that hook_field_delete() was not called yet.
    $mem = field_test_memorize();
    $this->assertEqual(count($mem), 0, 'No field hooks were called.');

    // Purge the data.
    field_purge_batch(10);

    // Check hooks invocations.
    // hook_field_load() and hook_field_delete() should have been called once
    // for each entity in the bundle.
    $actual_hooks = field_test_memorize();
    $hooks = array();
    $entities = $this->entities_by_bundles[$bundle];
    foreach ($entities as $id => $entity) {
      $hooks['field_test_field_load'][] = array($id => $entity);
      $hooks['field_test_field_delete'][] = $entity;
    }
    $this->checkHooksInvocations($hooks, $actual_hooks);

    // Purge again to purge the instance.
    field_purge_batch(0);

    // The field still exists, not deleted.
    $fields = field_read_fields(array('uuid' => $field->uuid), array('include_deleted' => TRUE));
    $this->assertTrue(isset($fields[$field->uuid]) && !$fields[$field->uuid]->deleted, 'The field exists and is not deleted');

    // Delete the second instance.
    $bundle = next($this->bundles);
    $instance = field_info_instance($this->entity_type, $field->id(), $bundle);
    $instance->delete();

    // Assert that hook_field_delete() was not called yet.
    $mem = field_test_memorize();
    $this->assertEqual(count($mem), 0, 'No field hooks were called.');

    // Purge the data.
    field_purge_batch(10);

    // Check hooks invocations (same as above, for the 2nd bundle).
    $actual_hooks = field_test_memorize();
    $hooks = array();
    $entities = $this->entities_by_bundles[$bundle];
    foreach ($entities as $id => $entity) {
      $hooks['field_test_field_load'][] = array($id => $entity);
      $hooks['field_test_field_delete'][] = $entity;
    }
    $this->checkHooksInvocations($hooks, $actual_hooks);

    // The field still exists, deleted.
    $fields = field_read_fields(array('uuid' => $field->uuid), array('include_deleted' => TRUE));
    $this->assertTrue(isset($fields[$field->uuid]) && $fields[$field->uuid]->deleted, 'The field exists and is deleted');

    // Purge again to purge the instance and the field.
    field_purge_batch(0);

    // The field is gone.
    $fields = field_read_fields(array('uuid' => $field->uuid), array('include_deleted' => TRUE, 'include_inactive' => TRUE));
    $this->assertEqual(count($fields), 0, 'The field is purged.');
  }

}
