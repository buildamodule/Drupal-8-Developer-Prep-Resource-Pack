<?php

/**
 * @file
 * Contains \Drupal\system\Tests\Entity\EntityValidationTest.
 */

namespace Drupal\system\Tests\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldInterface;
use Drupal\Core\Entity\Field\FieldItemInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Tests Entity API base functionality.
 */
class EntityValidationTest extends EntityUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('filter', 'text');

  public static function getInfo() {
    return array(
      'name' => 'Entity Validation API',
      'description' => 'Tests the Entity Validation API',
      'group' => 'Entity API',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('user', array('users_roles', 'users_data'));
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
   * @param string $entity_type
   *   An entity type.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created test entity.
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
   * Tests validating test entity types.
   */
  public function testValidation() {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->checkValidation($entity_type);
    }
  }

  /**
   * Executes the validation test set for a defined entity type.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   */
  protected function checkValidation($entity_type) {
    $entity = $this->createTestEntity($entity_type);
    $violations = $entity->validate();
    $this->assertEqual($violations->count(), 0, 'Validation passes.');

    // Test triggering a fail for each of the constraints specified.
    $test_entity = clone $entity;
    $test_entity->uuid->value = $this->randomString(129);
    $violations = $test_entity->validate();
    $this->assertEqual($violations->count(), 1, 'Validation failed.');
    $this->assertEqual($violations[0]->getMessage(), t('This value is too long. It should have %limit characters or less.', array('%limit' => '128')));

    $test_entity = clone $entity;
    $test_entity->langcode->value = $this->randomString(13);
    $violations = $test_entity->validate();
    $this->assertEqual($violations->count(), 1, 'Validation failed.');
    $this->assertEqual($violations[0]->getMessage(), t('This value is too long. It should have %limit characters or less.', array('%limit' => '12')));


    $test_entity = clone $entity;
    $test_entity->type->value = NULL;
    $violations = $test_entity->validate();
    $this->assertEqual($violations->count(), 1, 'Validation failed.');
    $this->assertEqual($violations[0]->getMessage(), t('This value should not be null.'));

    $test_entity = clone $entity;
    $test_entity->name->value = $this->randomString(33);
    $violations = $test_entity->validate();
    $this->assertEqual($violations->count(), 1, 'Validation failed.');
    $this->assertEqual($violations[0]->getMessage(), t('This value is too long. It should have %limit characters or less.', array('%limit' => '32')));

    // Make sure the information provided by a violation is correct.
    $violation = $violations[0];
    $this->assertEqual($violation->getRoot(), $test_entity, 'Violation root is entity.');
    $this->assertEqual($violation->getPropertyPath(), 'name.0.value', 'Violation property path is correct.');
    $this->assertEqual($violation->getInvalidValue(), $test_entity->name->value, 'Violation contains invalid value.');
  }

}
