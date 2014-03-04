<?php

/**
 * @file
 * Contains \Drupal\number\Tests\NumberItemTest.
 */

namespace Drupal\number\Tests;

use Drupal\Core\Entity\Field\FieldItemInterface;
use Drupal\Core\Entity\Field\FieldInterface;
use Drupal\field\Tests\FieldUnitTestBase;

/**
 * Tests the new entity API for the number field type.
 */
class NumberItemTest extends FieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('number');

  public static function getInfo() {
    return array(
      'name' => 'Number field items',
      'description' => 'Tests the new entity API for the number field types.',
      'group' => 'Field types',
    );
  }

  public function setUp() {
    parent::setUp();

    // Create number fields and instances for validation.
    foreach (array('integer', 'float', 'decimal') as $type) {
      entity_create('field_entity', array(
        'field_name' => 'field_' . $type,
        'type' => 'number_' . $type,
      ))->save();
      entity_create('field_instance', array(
        'entity_type' => 'entity_test',
        'field_name' => 'field_' . $type,
        'bundle' => 'entity_test',
      ))->save();
    }
  }

  /**
   * Tests using entity fields of the number field type.
   */
  public function testNumberItem() {
    // Verify entity creation.
    $entity = entity_create('entity_test', array());
    $integer = rand(0, 10);
    $entity->field_integer = $integer;
    $float = 3.14;
    $entity->field_float = $float;
    $decimal = '31.3';
    $entity->field_decimal = $decimal;
    $entity->name->value = $this->randomName();
    $entity->save();

    // Verify entity has been created properly.
    $id = $entity->id();
    $entity = entity_load('entity_test', $id);
    $this->assertTrue($entity->field_integer instanceof FieldInterface, 'Field implements interface.');
    $this->assertTrue($entity->field_integer[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEqual($entity->field_integer->value, $integer);
    $this->assertEqual($entity->field_integer[0]->value, $integer);
    $this->assertTrue($entity->field_float instanceof FieldInterface, 'Field implements interface.');
    $this->assertTrue($entity->field_float[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEqual($entity->field_float->value, $float);
    $this->assertEqual($entity->field_float[0]->value, $float);
    $this->assertTrue($entity->field_decimal instanceof FieldInterface, 'Field implements interface.');
    $this->assertTrue($entity->field_decimal[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEqual($entity->field_decimal->value, $decimal);
    $this->assertEqual($entity->field_decimal[0]->value, $decimal);

    // Verify changing the number value.
    $new_integer = rand(11, 20);
    $new_float = rand(1001, 2000) / 100;
    $new_decimal = '18.2';
    $entity->field_integer->value = $new_integer;
    $this->assertEqual($entity->field_integer->value, $new_integer);
    $entity->field_float->value = $new_float;
    $this->assertEqual($entity->field_float->value, $new_float);
    $entity->field_decimal->value = $new_decimal;
    $this->assertEqual($entity->field_decimal->value, $new_decimal);

    // Read changed entity and assert changed values.
    $entity->save();
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->field_integer->value, $new_integer);
    $this->assertEqual($entity->field_float->value, $new_float);
    $this->assertEqual($entity->field_decimal->value, $new_decimal);
  }

}
