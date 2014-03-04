<?php

/**
 * @file
 * Contains \Drupal\datetime\Tests\DateTimeItemTest.
 */

namespace Drupal\datetime\Tests;

use Drupal\Core\Entity\Field\FieldInterface;
use Drupal\Core\Entity\Field\FieldItemInterface;
use Drupal\field\Tests\FieldUnitTestBase;

/**
 * Tests the new entity API for the date field type.
 */
class DateTimeItemTest extends FieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('datetime');

  public static function getInfo() {
    return array(
      'name' => 'Date field item',
      'description' => 'Tests the new entity API for the Date field type.',
      'group' => 'Field types',
    );
  }

  public function setUp() {
    parent::setUp();

    // Create a field with settings to validate.
    $this->field = entity_create('field_entity', array(
      'field_name' => 'field_datetime',
      'type' => 'datetime',
      'settings' => array('datetime_type' => 'date'),
    ));
    $this->field->save();
    $this->instance = entity_create('field_instance', array(
      'field_name' => $this->field['field_name'],
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => array(
        'default_value' => 'blank',
      ),
    ));
    $this->instance->save();
  }

  /**
   * Tests using entity fields of the date field type.
   */
  public function testDateTimeItem() {
    // Verify entity creation.
    $entity = entity_create('entity_test', array());
    $value = '2014-01-01T20:00:00Z';
    $entity->field_datetime = $value;
    $entity->name->value = $this->randomName();
    $entity->save();

    // Verify entity has been created properly.
    $id = $entity->id();
    $entity = entity_load('entity_test', $id);
    $this->assertTrue($entity->field_datetime instanceof FieldInterface, 'Field implements interface.');
    $this->assertTrue($entity->field_datetime[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEqual($entity->field_datetime->value, $value);
    $this->assertEqual($entity->field_datetime[0]->value, $value);

    // Verify changing the date value.
    $new_value = $this->randomName();
    $entity->field_datetime->value = $new_value;
    $this->assertEqual($entity->field_datetime->value, $new_value);

    // Read changed entity and assert changed values.
    $entity->save();
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->field_datetime->value, $new_value);
  }

  /**
   * Tests DateTimeItem::setValue().
   */
  public function testSetValue() {
    // Test DateTimeItem::setValue() using string.
    $entity = entity_create('entity_test', array());
    $value = '2014-01-01T20:00:00Z';
    $entity->get('field_datetime')->offsetGet(0)->setValue($value);
    $entity->save();
    // Load the entity and ensure the field was saved correctly.
    $id = $entity->id();
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->field_datetime[0]->value, $value, 'DateTimeItem::setValue() works with string value.');

    // Test DateTimeItem::setValue() using property array.
    $entity = entity_create('entity_test', array());
    $value = '2014-01-01T20:00:00Z';
    $entity->get('field_datetime')->offsetGet(0)->setValue(array('value' => $value));
    $entity->save();
    // Load the entity and ensure the field was saved correctly.
    $id = $entity->id();
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->field_datetime[0]->value, $value, 'DateTimeItem::setValue() works with array value.');
  }

  /**
   * Tests setting the value of the DateTimeItem directly.
   */
  public function testSetValueProperty() {
    // Test Date::setValue().
    $entity = entity_create('entity_test', array());
    $value = '2014-01-01T20:00:00Z';

    $entity->get('field_datetime')->offsetGet(0)->get('value')->setValue($value);
    $entity->save();
    // Load the entity and ensure the field was saved correctly.
    $id = $entity->id();
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->field_datetime[0]->value, $value, '"Value" property can be set directly.');
  }

}
