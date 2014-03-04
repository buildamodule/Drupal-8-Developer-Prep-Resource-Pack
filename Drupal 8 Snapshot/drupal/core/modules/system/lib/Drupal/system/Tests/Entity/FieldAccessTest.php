<?php

/**
 * @file
 * Contains \Drupal\system\Tests\Entity\FieldAccessTest.
 */

namespace Drupal\system\Tests\Entity;

use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests the functionality of field access.
 */
class FieldAccessTest extends DrupalUnitTestBase {

  /**
   * Modules to load code from.
   *
   * @var array
   */
  public static $modules = array('entity', 'entity_test', 'field', 'field_sql_storage', 'system', 'text', 'user');

  /**
   * Holds the currently active global user ID that initiated the test run.
   *
   * The user ID gets replaced during the test and needs to be kept here so that
   * it can be restored at the end of the test run.
   *
   * @var int
   */
  protected $activeUid;

  public static function getInfo() {
    return array(
      'name' => 'Field access tests',
      'description' => 'Test Field level access hooks.',
      'group' => 'Entity API',
    );
  }

  protected function setUp() {
    parent::setUp();
    // Install field configuration.
    $this->installConfig(array('field'));
    // The users table is needed for creating dummy user accounts.
    $this->installSchema('user', array('users'));
    // Register entity_test text field.
    entity_test_install();
  }

  /**
   * Tests hook_entity_field_access() and hook_entity_field_access_alter().
   *
   * @see entity_test_entity_field_access()
   * @see entity_test_entity_field_access_alter()
   */
  function testFieldAccess() {
    $values = array(
      'name' => $this->randomName(),
      'user_id' => 1,
      'field_test_text' => array(
        'value' => 'no access value',
        'format' => 'full_html',
      ),
    );
    $entity = entity_create('entity_test', $values);

    // Create a dummy user account for testing access with.
    $values = array('name' => 'test');
    $account = entity_create('user', $values);

    $this->assertFalse($entity->field_test_text->access('view', $account->getNGEntity()), 'Access to the field was denied.');

    $entity->field_test_text = 'access alter value';
    $this->assertFalse($entity->field_test_text->access('view', $account->getNGEntity()), 'Access to the field was denied.');

    $entity->field_test_text = 'standard value';
    $this->assertTrue($entity->field_test_text->access('view', $account->getNGEntity()), 'Access to the field was granted.');
  }
}
