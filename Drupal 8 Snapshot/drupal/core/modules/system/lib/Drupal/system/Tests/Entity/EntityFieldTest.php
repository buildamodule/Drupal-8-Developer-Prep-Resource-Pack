<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Entity\EntityFieldTest.
 */

namespace Drupal\system\Tests\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldInterface;
use Drupal\Core\Entity\Field\FieldItemInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Tests Entity API base functionality.
 */
class EntityFieldTest extends EntityUnitTestBase  {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('filter', 'text', 'node');

  public static function getInfo() {
    return array(
      'name' => 'Entity Field API',
      'description' => 'Tests the Entity Field API',
      'group' => 'Entity API',
    );
  }

  public function setUp() {
    parent::setUp();
    $this->installSchema('user', array('users_roles', 'users_data'));
    $this->installSchema('node', array('node', 'node_field_data', 'node_field_revision', 'node_access'));
    $this->installSchema('entity_test', array(
      'entity_test_mul',
      'entity_test_mul_property_data',
      'entity_test_rev',
      'entity_test_rev_revision',
      'entity_test_mulrev',
      'entity_test_mulrev_property_data',
      'entity_test_mulrev_property_revision'
    ));

    // Create the test field.
    entity_test_install();

    // Install required default configuration for filter module.
    $this->installConfig(array('system', 'filter'));
  }

  /**
   * Creates a test entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  protected function createTestEntity($entity_type) {
    $this->entity_name = $this->randomName();
    $this->entity_user = $this->createUser();
    $this->entity_field_text = $this->randomName();

    // Pass in the value of the name field when creating. With the user
    // field we test setting a field after creation.
    $entity = entity_create($entity_type, array());
    $entity->user_id->target_id = $this->entity_user->id();
    $entity->name->value = $this->entity_name;

    // Set a value for the test field.
    $entity->field_test_text->value = $this->entity_field_text;

    return $entity;
  }

  /**
   * Tests reading and writing properties and field items.
   */
  public function testReadWrite() {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->assertReadWrite($entity_type);
    }
  }

  /**
   * Executes the read write test set for a defined entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   */
  protected function assertReadWrite($entity_type) {
    $entity = $this->createTestEntity($entity_type);

    // Access the name field.
    $this->assertTrue($entity->name instanceof FieldInterface, format_string('%entity_type: Field implements interface', array('%entity_type' => $entity_type)));
    $this->assertTrue($entity->name[0] instanceof FieldItemInterface, format_string('%entity_type: Field item implements interface', array('%entity_type' => $entity_type)));

    $this->assertEqual($this->entity_name, $entity->name->value, format_string('%entity_type: Name value can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($this->entity_name, $entity->name[0]->value, format_string('%entity_type: Name value can be read through list access.', array('%entity_type' => $entity_type)));
    $this->assertEqual($entity->name->getValue(), array(0 => array('value' => $this->entity_name)), format_string('%entity_type: Plain field value returned.', array('%entity_type' => $entity_type)));

    // Change the name.
    $new_name = $this->randomName();
    $entity->name->value = $new_name;
    $this->assertEqual($new_name, $entity->name->value, format_string('%entity_type: Name can be updated and read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($entity->name->getValue(), array(0 => array('value' => $new_name)), format_string('%entity_type: Plain field value reflects the update.', array('%entity_type' => $entity_type)));

    $new_name = $this->randomName();
    $entity->name[0]->value = $new_name;
    $this->assertEqual($new_name, $entity->name->value, format_string('%entity_type: Name can be updated and read through list access.', array('%entity_type' => $entity_type)));

    // Access the user field.
    $this->assertTrue($entity->user_id instanceof FieldInterface, format_string('%entity_type: Field implements interface', array('%entity_type' => $entity_type)));
    $this->assertTrue($entity->user_id[0] instanceof FieldItemInterface, format_string('%entity_type: Field item implements interface', array('%entity_type' => $entity_type)));

    $this->assertEqual($this->entity_user->id(), $entity->user_id->target_id, format_string('%entity_type: User id can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($this->entity_user->getUsername(), $entity->user_id->entity->name->value, format_string('%entity_type: User name can be read.', array('%entity_type' => $entity_type)));

    // Change the assigned user by entity.
    $new_user = $this->createUser();
    $entity->user_id->entity = $new_user;
    $this->assertEqual($new_user->id(), $entity->user_id->target_id, format_string('%entity_type: Updated user id can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($new_user->getUsername(), $entity->user_id->entity->name->value, format_string('%entity_type: Updated user name value can be read.', array('%entity_type' => $entity_type)));

    // Change the assigned user by id.
    $new_user = $this->createUser();
    $entity->user_id->target_id = $new_user->id();
    $this->assertEqual($new_user->id(), $entity->user_id->target_id, format_string('%entity_type: Updated user id can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($new_user->getUsername(), $entity->user_id->entity->name->value, format_string('%entity_type: Updated user name value can be read.', array('%entity_type' => $entity_type)));

    // Try unsetting a field.
    $entity->name->value = NULL;
    $entity->user_id->target_id = NULL;
    $this->assertNull($entity->name->value, format_string('%entity_type: Name field is not set.', array('%entity_type' => $entity_type)));
    $this->assertNull($entity->user_id->target_id, format_string('%entity_type: User ID field is not set.', array('%entity_type' => $entity_type)));
    $this->assertNull($entity->user_id->entity, format_string('%entity_type: User entity field is not set.', array('%entity_type' => $entity_type)));

    // Test using isset(), empty() and unset().
    $entity->name->value = 'test unset';
    unset($entity->name->value);
    $this->assertFalse(isset($entity->name->value), format_string('%entity_type: Name is not set.', array('%entity_type' => $entity_type)));
    $this->assertFalse(isset($entity->name[0]->value), format_string('%entity_type: Name is not set.', array('%entity_type' => $entity_type)));
    $this->assertTrue(empty($entity->name->value), format_string('%entity_type: Name is empty.', array('%entity_type' => $entity_type)));
    $this->assertTrue(empty($entity->name[0]->value), format_string('%entity_type: Name is empty.', array('%entity_type' => $entity_type)));

    $entity->name->value = 'a value';
    $this->assertTrue(isset($entity->name->value), format_string('%entity_type: Name is set.', array('%entity_type' => $entity_type)));
    $this->assertTrue(isset($entity->name[0]->value), format_string('%entity_type: Name is set.', array('%entity_type' => $entity_type)));
    $this->assertFalse(empty($entity->name->value), format_string('%entity_type: Name is not empty.', array('%entity_type' => $entity_type)));
    $this->assertFalse(empty($entity->name[0]->value), format_string('%entity_type: Name is not empty.', array('%entity_type' => $entity_type)));
    $this->assertTrue(isset($entity->name[0]), format_string('%entity_type: Name string item is set.', array('%entity_type' => $entity_type)));
    $this->assertFalse(isset($entity->name[1]), format_string('%entity_type: Second name string item is not set as it does not exist', array('%entity_type' => $entity_type)));
    $this->assertTrue(isset($entity->name), format_string('%entity_type: Name field is set.', array('%entity_type' => $entity_type)));
    $this->assertFalse(isset($entity->nameInvalid), format_string('%entity_type: Not existing field is not set.', array('%entity_type' => $entity_type)));

    unset($entity->name[0]);
    $this->assertFalse(isset($entity->name[0]), format_string('%entity_type: Name field item is not set.', array('%entity_type' => $entity_type)));
    $this->assertFalse(isset($entity->name[0]->value), format_string('%entity_type: Name is not set.', array('%entity_type' => $entity_type)));
    $this->assertFalse(isset($entity->name->value), format_string('%entity_type: Name is not set.', array('%entity_type' => $entity_type)));

    $entity->name = array();
    $this->assertTrue(isset($entity->name), 'Name field is set.');
    $this->assertFalse(isset($entity->name[0]), 'Name field item is not set.');
    $this->assertFalse(isset($entity->name[0]->value), 'First name item value is not set.');
    $this->assertFalse(isset($entity->name->value), 'Name value is not set.');

    $entity->name = NULL;
    $this->assertFalse(isset($entity->name), 'Name field is not set.');
    $this->assertFalse(isset($entity->name[0]), 'Name field item is not set.');
    $this->assertFalse(isset($entity->name[0]->value), 'First name item value is not set.');
    $this->assertFalse(isset($entity->name->value), 'Name value is not set.');

    $entity->name->value = 'a value';
    $this->assertTrue(isset($entity->name->value), format_string('%entity_type: Name is set.', array('%entity_type' => $entity_type)));
    unset($entity->name);
    $this->assertFalse(isset($entity->name), format_string('%entity_type: Name field is not set.', array('%entity_type' => $entity_type)));
    $this->assertFalse(isset($entity->name[0]), format_string('%entity_type: Name field item is not set.', array('%entity_type' => $entity_type)));
    $this->assertFalse(isset($entity->name[0]->value), format_string('%entity_type: Name is not set.', array('%entity_type' => $entity_type)));
    $this->assertFalse(isset($entity->name->value), format_string('%entity_type: Name is not set.', array('%entity_type' => $entity_type)));

    // Access the language field.
    $this->assertEqual(Language::LANGCODE_NOT_SPECIFIED, $entity->langcode->value, format_string('%entity_type: Language code can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual(language_load(Language::LANGCODE_NOT_SPECIFIED), $entity->langcode->language, format_string('%entity_type: Language object can be read.', array('%entity_type' => $entity_type)));

    // Change the language by code.
    $entity->langcode->value = language_default()->id;
    $this->assertEqual(language_default()->id, $entity->langcode->value, format_string('%entity_type: Language code can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual(language_default(), $entity->langcode->language, format_string('%entity_type: Language object can be read.', array('%entity_type' => $entity_type)));

    // Revert language by code then try setting it by language object.
    $entity->langcode->value = Language::LANGCODE_NOT_SPECIFIED;
    $entity->langcode->language = language_default();
    $this->assertEqual(language_default()->id, $entity->langcode->value, format_string('%entity_type: Language code can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual(language_default(), $entity->langcode->language, format_string('%entity_type: Language object can be read.', array('%entity_type' => $entity_type)));

    // Access the text field and test updating.
    $this->assertEqual($entity->field_test_text->value, $this->entity_field_text, format_string('%entity_type: Text field can be read.', array('%entity_type' => $entity_type)));
    $new_text = $this->randomName();
    $entity->field_test_text->value = $new_text;
    $this->assertEqual($entity->field_test_text->value, $new_text, format_string('%entity_type: Updated text field can be read.', array('%entity_type' => $entity_type)));

    // Test creating the entity by passing in plain values.
    $this->entity_name = $this->randomName();
    $name_item[0]['value'] = $this->entity_name;
    $this->entity_user = $this->createUser();
    $user_item[0]['target_id'] = $this->entity_user->id();
    $this->entity_field_text = $this->randomName();
    $text_item[0]['value'] = $this->entity_field_text;

    $entity = entity_create($entity_type, array(
      'name' => $name_item,
      'user_id' => $user_item,
      'field_test_text' => $text_item,
    ));
    $this->assertEqual($this->entity_name, $entity->name->value, format_string('%entity_type: Name value can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($this->entity_user->id(), $entity->user_id->target_id, format_string('%entity_type: User id can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($this->entity_user->getUsername(), $entity->user_id->entity->name->value, format_string('%entity_type: User name can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($this->entity_field_text, $entity->field_test_text->value, format_string('%entity_type: Text field can be read.', array('%entity_type' => $entity_type)));

    // Test copying field values.
    $entity2 = $this->createTestEntity($entity_type);
    $entity2->name = $entity->name;
    $entity2->user_id = $entity->user_id;
    $entity2->field_test_text = $entity->field_test_text;

    $this->assertTrue($entity->name !== $entity2->name, format_string('%entity_type: Copying properties results in a different field object.', array('%entity_type' => $entity_type)));
    $this->assertEqual($entity->name->value, $entity2->name->value, format_string('%entity_type: Name field copied.', array('%entity_type' => $entity_type)));
    $this->assertEqual($entity->user_id->target_id, $entity2->user_id->target_id, format_string('%entity_type: User id field copied.', array('%entity_type' => $entity_type)));
    $this->assertEqual($entity->field_test_text->value, $entity2->field_test_text->value, format_string('%entity_type: Text field copied.', array('%entity_type' => $entity_type)));

    // Tests adding a value to a field item list.
    $entity->name[] = 'Another name';
    $this->assertEqual($entity->name[1]->value == 'Another name', format_string('%entity_type: List item added via [].', array('%entity_type' => $entity_type)));
    $entity->name[2]->value = 'Third name';
    $this->assertEqual($entity->name[2]->value == 'Third name', format_string('%entity_type: List item added by a accessing not yet created item.', array('%entity_type' => $entity_type)));

    // Test removing and empty-ing list items.
    $this->assertEqual(count($entity->name), 3, format_string('%entity_type: List has 3 items.', array('%entity_type' => $entity_type)));
    unset($entity->name[1]);
    $this->assertEqual(count($entity->name), 2, format_string('%entity_type: Second list item has been removed.', array('%entity_type' => $entity_type)));
    $entity->name[2] = NULL;
    $this->assertEqual(count($entity->name), 2, format_string('%entity_type: Assigning NULL does not reduce array count.', array('%entity_type' => $entity_type)));
    $this->assertTrue($entity->name[2]->isEmpty(), format_string('%entity_type: Assigning NULL empties the item.', array('%entity_type' => $entity_type)));

    // Test using isEmpty().
    unset($entity->name[2]);
    $this->assertFalse($entity->name[0]->isEmpty(), format_string('%entity_type: Name item is not empty.', array('%entity_type' => $entity_type)));
    $entity->name->value = NULL;
    $this->assertTrue($entity->name[0]->isEmpty(), format_string('%entity_type: Name item is empty.', array('%entity_type' => $entity_type)));
    $this->assertTrue($entity->name->isEmpty(), format_string('%entity_type: Name field is empty.', array('%entity_type' => $entity_type)));
    $this->assertEqual(count($entity->name), 1, format_string('%entity_type: Empty item is considered when counting.', array('%entity_type' => $entity_type)));
    $this->assertEqual(count(iterator_to_array($entity->name->getIterator())), count($entity->name), format_string('%entity_type: Count matches iterator count.', array('%entity_type' => $entity_type)));
    $this->assertTrue($entity->name->getValue() === array(0 => array('value' => NULL)), format_string('%entity_type: Name field value contains a NULL value.', array('%entity_type' => $entity_type)));

    // Test removing all list items by assigning an empty array.
    $entity->name = array();
    $this->assertIdentical(count($entity->name), 0, format_string('%entity_type: Name field contains no items.', array('%entity_type' => $entity_type)));
    $this->assertIdentical($entity->name->getValue(), array(), format_string('%entity_type: Name field value is an empty array.', array('%entity_type' => $entity_type)));

    $entity->name->value = 'foo';
    $this->assertEqual($entity->name->value, 'foo', format_string('%entity_type: Name field set.', array('%entity_type' => $entity_type)));
    // Test removing all list items by setting it to NULL.
    $entity->name = NULL;
    $this->assertIdentical(count($entity->name), 0, format_string('%entity_type: Name field contains no items.', array('%entity_type' => $entity_type)));
    $this->assertNull($entity->name->getValue(), format_string('%entity_type: Name field value is an empty array.', array('%entity_type' => $entity_type)));

    // Test get and set field values.
    $entity->name = 'foo';
    $this->assertEqual($entity->name[0]->getPropertyValues(), array('value' => 'foo'), format_string('%entity_type: Field value has been retrieved via getPropertyValue()', array('%entity_type' => $entity_type)));
    $entity->name[0]->setPropertyValues(array('value' => 'bar'));
    $this->assertEqual($entity->name->value, 'bar', format_string('%entity_type: Field value has been set via setPropertyValue()', array('%entity_type' => $entity_type)));

    $values = $entity->getPropertyValues();
    $this->assertEqual($values['name'], array(0 => array('value' => 'bar')), format_string('%entity_type: Field value has been retrieved via getPropertyValue() from an entity.', array('%entity_type' => $entity_type)));
    $entity->setPropertyValues(array('name' => 'foo'));
    $this->assertEqual($entity->name->value, 'foo', format_string('%entity_type: Field value has been set via setPropertyValue() on an entity.', array('%entity_type' => $entity_type)));

    // Make sure the user id can be set to zero.
    $user_item[0]['target_id'] = 0;
    $entity = entity_create($entity_type, array(
      'name' => $name_item,
      'user_id' => $user_item,
      'field_test_text' => $text_item,
    ));
    $this->assertNotNull($entity->user_id->target_id, format_string('%entity_type: User id is not NULL', array('%entity_type' => $entity_type)));
    $this->assertIdentical($entity->user_id->target_id, 0, format_string('%entity_type: User id has been set to 0', array('%entity_type' => $entity_type)));

    // Test setting the ID with the value only.
    $entity = entity_create($entity_type, array(
      'name' => $name_item,
      'user_id' => 0,
      'field_test_text' => $text_item,
    ));
    $this->assertNotNull($entity->user_id->target_id, format_string('%entity_type: User id is not NULL', array('%entity_type' => $entity_type)));
    $this->assertIdentical($entity->user_id->target_id, 0, format_string('%entity_type: User id has been set to 0', array('%entity_type' => $entity_type)));
  }

  /**
   * Tries to save and load an entity again.
   */
  public function testSave() {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->assertSave($entity_type);
    }
  }

  /**
   * Executes the save tests for the given entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   */
  protected function assertSave($entity_type) {
    $entity = $this->createTestEntity($entity_type);
    $entity->save();
    $this->assertTrue((bool) $entity->id(), format_string('%entity_type: Entity has received an id.', array('%entity_type' => $entity_type)));

    $entity = entity_load($entity_type, $entity->id());
    $this->assertTrue((bool) $entity->id(), format_string('%entity_type: Entity loaded.', array('%entity_type' => $entity_type)));

    // Access the name field.
    $this->assertEqual(1, $entity->id->value, format_string('%entity_type: ID value can be read.', array('%entity_type' => $entity_type)));
    $this->assertTrue(is_string($entity->uuid->value), format_string('%entity_type: UUID value can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual(Language::LANGCODE_NOT_SPECIFIED, $entity->langcode->value, format_string('%entity_type: Language code can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual(language_load(Language::LANGCODE_NOT_SPECIFIED), $entity->langcode->language, format_string('%entity_type: Language object can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($this->entity_user->id(), $entity->user_id->target_id, format_string('%entity_type: User id can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($this->entity_user->getUsername(), $entity->user_id->entity->name->value, format_string('%entity_type: User name can be read.', array('%entity_type' => $entity_type)));
    $this->assertEqual($this->entity_field_text, $entity->field_test_text->value, format_string('%entity_type: Text field can be read.', array('%entity_type' => $entity_type)));
  }

  /**
   * Tests introspection and getting metadata upfront.
   */
  public function testIntrospection() {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->checkIntrospection($entity_type);
    }
  }

  /**
   * Executes the introspection tests for the given entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   */
  protected function checkIntrospection($entity_type) {
    // Test getting metadata upfront.
    // @todo: Make this work without having to create entity objects.
    $entity = entity_create($entity_type, array());
    $definitions = $entity->getPropertyDefinitions();
    $this->assertEqual($definitions['name']['type'], 'string_field', $entity_type .': Name field found.');
    $this->assertEqual($definitions['user_id']['type'], 'entity_reference_field', $entity_type .': User field found.');
    $this->assertEqual($definitions['field_test_text']['type'], 'field_item:text', $entity_type .': Test-text-field field found.');

    // Test introspecting an entity object.
    // @todo: Add bundles and test bundles as well.
    $entity = entity_create($entity_type, array());

    $definitions = $entity->getPropertyDefinitions();
    $this->assertEqual($definitions['name']['type'], 'string_field', $entity_type .': Name field found.');
    $this->assertEqual($definitions['user_id']['type'], 'entity_reference_field', $entity_type .': User field found.');
    $this->assertEqual($definitions['field_test_text']['type'], 'field_item:text', $entity_type .': Test-text-field field found.');

    $name_properties = $entity->name->getPropertyDefinitions();
    $this->assertEqual($name_properties['value']['type'], 'string', $entity_type .': String value property of the name found.');

    $userref_properties = $entity->user_id->getPropertyDefinitions();
    $this->assertEqual($userref_properties['target_id']['type'], 'integer', $entity_type .': Entity id property of the user found.');
    $this->assertEqual($userref_properties['entity']['type'], 'entity_reference', $entity_type .': Entity reference property of the user found.');

    $textfield_properties = $entity->field_test_text->getPropertyDefinitions();
    $this->assertEqual($textfield_properties['value']['type'], 'string', $entity_type .': String value property of the test-text field found.');
    $this->assertEqual($textfield_properties['format']['type'], 'string', $entity_type .': String format field of the test-text field found.');
    $this->assertEqual($textfield_properties['processed']['type'], 'string', $entity_type .': String processed property of the test-text field found.');

    // @todo: Once the user entity has definitions, continue testing getting
    // them from the $userref_values['entity'] property.

    // Make sure provided contextual information is right.
    $this->assertIdentical($entity->getRoot(), $entity, 'Entity is root object.');
    $this->assertEqual($entity->getPropertyPath(), '');
    $this->assertEqual($entity->getName(), '');
    $this->assertEqual($entity->getParent(), NULL);

    $field = $entity->user_id;
    $this->assertIdentical($field->getRoot(), $entity, 'Entity is root object.');
    $this->assertEqual($field->getPropertyPath(), 'user_id');
    $this->assertEqual($field->getName(), 'user_id');
    $this->assertIdentical($field->getParent(), $entity, 'Parent object matches.');

    $field_item = $field[0];
    $this->assertIdentical($field_item->getRoot(), $entity, 'Entity is root object.');
    $this->assertEqual($field_item->getPropertyPath(), 'user_id.0');
    $this->assertEqual($field_item->getName(), '0');
    $this->assertIdentical($field_item->getParent(), $field, 'Parent object matches.');

    $item_value = $field_item->get('entity');
    $this->assertIdentical($item_value->getRoot(), $entity, 'Entity is root object.');
    $this->assertEqual($item_value->getPropertyPath(), 'user_id.0.entity');
    $this->assertEqual($item_value->getName(), 'entity');
    $this->assertIdentical($item_value->getParent(), $field_item, 'Parent object matches.');
  }

  /**
   * Tests iterating over properties.
   */
  public function testIterator() {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->assertIterator($entity_type);
    }
  }

  /**
   * Executes the iterator tests for the given entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   */
  protected function assertIterator($entity_type) {
    $entity = $this->createTestEntity($entity_type);

    foreach ($entity as $name => $field) {
      $this->assertTrue($field instanceof FieldInterface, $entity_type . ": Field $name implements interface.");

      foreach ($field as $delta => $item) {
        $this->assertTrue($field[0] instanceof FieldItemInterface, $entity_type . ": Item $delta of field $name implements interface.");

        foreach ($item as $value_name => $value_property) {
          $this->assertTrue($value_property instanceof TypedDataInterface, $entity_type . ": Value $value_name of item $delta of field $name implements interface.");

          $value = $value_property->getValue();
          $this->assertTrue(!isset($value) || is_scalar($value) || $value instanceof EntityInterface, $entity_type . ": Value $value_name of item $delta of field $name is a primitive or an entity.");
        }
      }
    }

    $properties = $entity->getProperties();
    $this->assertEqual(array_keys($properties), array_keys($entity->getPropertyDefinitions()), format_string('%entity_type: All properties returned.', array('%entity_type' => $entity_type)));
    $this->assertEqual($properties, iterator_to_array($entity->getIterator()), format_string('%entity_type: Entity iterator iterates over all properties.', array('%entity_type' => $entity_type)));
  }

  /**
   * Tests working with the entity based upon the TypedData API.
   */
  public function testDataStructureInterfaces() {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->assertDataStructureInterfaces($entity_type);
    }
  }

  /**
   * Executes the data structure interfaces tests for the given entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   */
  protected function assertDataStructureInterfaces($entity_type) {
    $entity = $this->createTestEntity($entity_type);

    // Test using the whole tree of typed data by navigating through the tree of
    // contained properties and getting all contained strings, limited by a
    // certain depth.
    $strings = array();
    $this->getContainedStrings($entity, 0, $strings);

    // @todo: Once the user entity has defined properties this should contain
    // the user name and other user entity strings as well.
    $target_strings = array(
      $entity->uuid->value,
      Language::LANGCODE_NOT_SPECIFIED,
      $this->entity_name,
      // Bundle name.
      $entity->bundle(),
      $this->entity_field_text,
      // Field format.
      NULL,
    );
    $this->assertEqual($strings, $target_strings, format_string('%entity_type: All contained strings found.', array('%entity_type' => $entity_type)));
  }

  /**
   * Recursive helper for getting all contained strings,
   * i.e. properties of type string.
   */
  public function getContainedStrings(TypedDataInterface $wrapper, $depth, array &$strings) {

    if ($wrapper->getType() == 'string') {
      $strings[] = $wrapper->getValue();
    }

    // Recurse until a certain depth is reached if possible.
    if ($depth < 7) {
      if ($wrapper instanceof \Drupal\Core\TypedData\ListInterface) {
        foreach ($wrapper as $item) {
          $this->getContainedStrings($item, $depth + 1, $strings);
        }
      }
      elseif ($wrapper instanceof \Drupal\Core\TypedData\ComplexDataInterface) {
        foreach ($wrapper as $name => $property) {
          $this->getContainedStrings($property, $depth + 1, $strings);
        }
      }
    }
  }

  /**
   * Makes sure data types are correctly derived for all entity types.
   */
  public function testDataTypes() {
    $types = \Drupal::typedData()->getDefinitions();
    foreach (entity_test_entity_types() as $entity_type) {
      $this->assertTrue($types['entity:' . $entity_type]['class'], 'Entity data type registed.');
    }
    // Check bundle types are provided as well.
    entity_test_create_bundle('bundle');
    $types = \Drupal::typedData()->getDefinitions();
    $this->assertTrue($types['entity:entity_test:bundle']['class'], 'Entity bundle data type registed.');
  }

  /**
   * Tests validation constraints provided by the Entity API.
   */
  public function testEntityConstraintValidation() {
    $entity = $this->createTestEntity('entity_test');
    $entity->save();
    // Create a reference field item and let it reference the entity.
    $definition = array(
      'type' => 'entity_reference_field',
      'settings' => array(
        'target_type' => 'entity_test',
      ),
      'label' => 'Test entity',
    );
    $reference_field_item = \Drupal::TypedData()->create($definition);
    $reference = $reference_field_item->get('entity');
    $reference->setValue($entity);

    // Test validation the typed data object.
    $violations = $reference->validate();
    $this->assertEqual($violations->count(), 0);

    // Test validating an entity of the wrong type.
    $user = $this->createUser();
    $user->save();
    $node = entity_create('node', array(
      'type' => 'page',
      'uid' => $user->id(),
    ));
    $reference->setValue($node);
    $violations = $reference->validate();
    $this->assertEqual($violations->count(), 1);

    // Test bundle validation.
    $definition = array(
      'type' => 'entity_reference_field',
      'settings' => array(
        'target_type' => 'node',
        'target_bundle' => 'article',
      ),
    );
    $reference_field_item = \Drupal::TypedData()->create($definition);
    $reference = $reference_field_item->get('entity');
    $reference->setValue($node);
    $violations = $reference->validate();
    $this->assertEqual($violations->count(), 1);

    $node = entity_create('node', array(
      'type' => 'article',
      'uid' => $user->id(),
    ));
    $node->save();
    $reference->setValue($node);
    $violations = $reference->validate();
    $this->assertEqual($violations->count(), 0);
  }

  /**
   * Tests getting processed property values via a computed property.
   */
  public function testComputedProperties() {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->assertComputedProperties($entity_type);
    }
  }

  /**
   * Executes the computed properties tests for the given entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   */
  protected function assertComputedProperties($entity_type) {
    // Make the test text field processed.
    $instance = field_info_instance($entity_type, 'field_test_text', $entity_type);
    $instance->settings['text_processing'] = 1;
    $instance->save();

    $entity = $this->createTestEntity($entity_type);
    $entity->field_test_text->value = "The <strong>text</strong> text to filter.";
    $entity->field_test_text->format = filter_default_format();

    $target = "<p>The &lt;strong&gt;text&lt;/strong&gt; text to filter.</p>\n";
    $this->assertEqual($entity->field_test_text->processed, $target, format_string('%entity_type: Text is processed with the default filter.', array('%entity_type' => $entity_type)));

    // Save and load entity and make sure it still works.
    $entity->save();
    $entity = entity_load($entity_type, $entity->id());
    $this->assertEqual($entity->field_test_text->processed, $target, format_string('%entity_type: Text is processed with the default filter.', array('%entity_type' => $entity_type)));
  }
}
