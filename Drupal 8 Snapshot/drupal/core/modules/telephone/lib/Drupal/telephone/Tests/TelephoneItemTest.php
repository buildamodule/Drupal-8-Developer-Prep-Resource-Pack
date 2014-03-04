<?php

/**
 * @file
 * Contains \Drupal\field\Tests\TelephoneItemTest.
 */

namespace Drupal\telephone\Tests;

use Drupal\Core\Entity\Field\FieldInterface;
use Drupal\Core\Entity\Field\FieldItemInterface;
use Drupal\field\Tests\FieldUnitTestBase;

/**
 * Tests the new entity API for the telephone field type.
 */
class TelephoneItemTest extends FieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('telephone');

  public static function getInfo() {
    return array(
      'name' => 'Telephone field item',
      'description' => 'Tests the new entity API for the telephone field type.',
      'group' => 'Field types',
    );
  }

  public function setUp() {
    parent::setUp();

    // Create a telephone field and instance for validation.
    entity_create('field_entity', array(
      'field_name' => 'field_test',
      'type' => 'telephone',
    ))->save();
    entity_create('field_instance', array(
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'bundle' => 'entity_test',
    ))->save();
  }

  /**
   * Tests using entity fields of the telephone field type.
   */
  public function testTestItem() {
    // Verify entity creation.
    $entity = entity_create('entity_test', array());
    $value = '+0123456789';
    $entity->field_test = $value;
    $entity->name->value = $this->randomName();
    $entity->save();

    // Verify entity has been created properly.
    $id = $entity->id();
    $entity = entity_load('entity_test', $id);
    $this->assertTrue($entity->field_test instanceof FieldInterface, 'Field implements interface.');
    $this->assertTrue($entity->field_test[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEqual($entity->field_test->value, $value);
    $this->assertEqual($entity->field_test[0]->value, $value);

    // Verify changing the field value.
    $new_value = '+41' . rand(1000000, 9999999);
    $entity->field_test->value = $new_value;
    $this->assertEqual($entity->field_test->value, $new_value);

    // Read changed entity and assert changed values.
    $entity->save();
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->field_test->value, $new_value);
  }

}
