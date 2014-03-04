<?php

/**
 * @file
 * Contains \Drupal\simpletest\Tests\DrupalUnitTestBaseTest.
 */

namespace Drupal\simpletest\Tests;

use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests DrupalUnitTestBase functionality.
 */
class DrupalUnitTestBaseTest extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('entity_test');

  public static function getInfo() {
    return array(
      'name' => 'DrupalUnitTestBase',
      'description' => 'Tests DrupalUnitTestBase functionality.',
      'group' => 'SimpleTest',
    );
  }

  /**
   * Tests expected behavior of setUp().
   */
  function testSetUp() {
    $module = 'entity_test';
    $table = 'entity_test';

    // Verify that specified $modules have been loaded.
    $this->assertTrue(function_exists('entity_test_permission'), "$module.module was loaded.");
    // Verify that there is a fixed module list.
    $this->assertIdentical(array_keys(\Drupal::moduleHandler()->getModuleList()), array($module));
    $this->assertIdentical(\Drupal::moduleHandler()->getImplementations('permission'), array($module));

    // Verify that no modules have been installed.
    $this->assertFalse(db_table_exists($table), "'$table' database table not found.");
  }

  /**
   * Tests expected load behavior of enableModules().
   */
  function testEnableModulesLoad() {
    $module = 'field_test';

    // Verify that the module does not exist yet.
    $this->assertFalse(module_exists($module), "$module module not found.");
    $list = array_keys(\Drupal::moduleHandler()->getModuleList());
    $this->assertFalse(in_array($module, $list), "$module module not found in the extension handler's module list.");
    $list = \Drupal::moduleHandler()->getImplementations('permission');
    $this->assertFalse(in_array($module, $list), "{$module}_permission() in Drupal::moduleHandler()->getImplementations() not found.");

    // Enable the module.
    $this->enableModules(array($module));

    // Verify that the module exists.
    $this->assertTrue(module_exists($module), "$module module found.");
    $list = array_keys(\Drupal::moduleHandler()->getModuleList());
    $this->assertTrue(in_array($module, $list), "$module module found in the extension handler's module list.");
    $list = \Drupal::moduleHandler()->getImplementations('permission');
    $this->assertTrue(in_array($module, $list), "{$module}_permission() in Drupal::moduleHandler()->getImplementations() found.");
  }

  /**
   * Tests expected installation behavior of enableModules().
   */
  function testEnableModulesInstall() {
    $module = 'node';
    $table = 'node';

    // Verify that the module does not exist yet.
    $this->assertFalse(module_exists($module), "$module module not found.");
    $list = array_keys(\Drupal::moduleHandler()->getModuleList());
    $this->assertFalse(in_array($module, $list), "$module module not found in the extension handler's module list.");
    $list = \Drupal::moduleHandler()->getImplementations('permission');
    $this->assertFalse(in_array($module, $list), "{$module}_permission() in Drupal::moduleHandler()->getImplementations() not found.");

    $this->assertFalse(db_table_exists($table), "'$table' database table not found.");
    $schema = drupal_get_schema($table);
    $this->assertFalse($schema, "'$table' table schema not found.");

    // Install the module.
    module_enable(array($module));

    // Verify that the enabled module exists.
    $this->assertTrue(module_exists($module), "$module module found.");
    $list = array_keys(\Drupal::moduleHandler()->getModuleList());
    $this->assertTrue(in_array($module, $list), "$module module found in the extension handler's module list.");
    $list = \Drupal::moduleHandler()->getImplementations('permission');
    $this->assertTrue(in_array($module, $list), "{$module}_permission() in Drupal::moduleHandler()->getImplementations() found.");

    $this->assertTrue(db_table_exists($table), "'$table' database table found.");
    $schema = drupal_get_schema($table);
    $this->assertTrue($schema, "'$table' table schema found.");
  }

  /**
   * Tests installing modules with DependencyInjection services.
   */
  function testEnableModulesInstallContainer() {
    // Install Node module.
    $this->enableModules(array('field_sql_storage', 'field', 'node'));

    $this->installSchema('node', array('node', 'node_field_data'));
    // Perform an entity query against node.
    $query = \Drupal::entityQuery('node');
    // Disable node access checks, since User module is not enabled.
    $query->accessCheck(FALSE);
    $query->condition('nid', 1);
    $query->execute();
    $this->pass('Entity field query was executed.');
  }

  /**
   * Tests expected behavior of installSchema().
   */
  function testInstallSchema() {
    $module = 'entity_test';
    $table = 'entity_test';
    // Verify that we can install a table from the module schema.
    $this->installSchema($module, $table);
    $this->assertTrue(db_table_exists($table), "'$table' database table found.");

    // Verify that the schema is known to Schema API.
    $schema = drupal_get_schema();
    $this->assertTrue($schema[$table], "'$table' table found in schema.");
    $schema = drupal_get_schema($table);
    $this->assertTrue($schema, "'$table' table schema found.");

    // Verify that a unknown table from an enabled module throws an error.
    $table = 'unknown_entity_test_table';
    try {
      $this->installSchema($module, $table);
      $this->fail('Exception for non-retrievable schema found.');
    }
    catch (\Exception $e) {
      $this->pass('Exception for non-retrievable schema found.');
    }
    $this->assertFalse(db_table_exists($table), "'$table' database table not found.");
    $schema = drupal_get_schema($table);
    $this->assertFalse($schema, "'$table' table schema not found.");

    // Verify that a table from a unknown module cannot be installed.
    $module = 'database_test';
    $table = 'test';
    try {
      $this->installSchema($module, $table);
      $this->fail('Exception for non-retrievable schema found.');
    }
    catch (\Exception $e) {
      $this->pass('Exception for non-retrievable schema found.');
    }
    $this->assertFalse(db_table_exists($table), "'$table' database table not found.");
    $schema = drupal_get_schema($table);
    $this->assertFalse($schema, "'$table' table schema not found.");

    // Verify that the same table can be installed after enabling the module.
    $this->enableModules(array($module));
    $this->installSchema($module, $table);
    $this->assertTrue(db_table_exists($table), "'$table' database table found.");
    $schema = drupal_get_schema($table);
    $this->assertTrue($schema, "'$table' table schema found.");
  }

  /**
   * Tests expected behavior of installConfig().
   */
  function testInstallConfig() {
    $module = 'user';

    // Verify that default config can only be installed for enabled modules.
    try {
      $this->installConfig(array($module));
      $this->fail('Exception for non-enabled module found.');
    }
    catch (\Exception $e) {
      $this->pass('Exception for non-enabled module found.');
    }
    $this->assertFalse($this->container->get('config.storage')->exists('user.settings'));

    // Verify that default config can be installed.
    $this->enableModules(array('user'));
    $this->installConfig(array('user'));
    $this->assertTrue($this->container->get('config.storage')->exists('user.settings'));
    $this->assertTrue(\Drupal::config('user.settings')->get('register'));
  }

  /**
   * Tests that the module list is retained after enabling/installing/disabling.
   */
  function testEnableModulesFixedList() {
    // entity_test is loaded via $modules; its entity type should exist.
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == entity_get_info('entity_test'));

    // Load some additional modules; entity_test should still exist.
    $this->enableModules(array('entity', 'field', 'field_sql_storage', 'text', 'entity_test'));
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == entity_get_info('entity_test'));

    // Install some other modules; entity_test should still exist.
    module_enable(array('field', 'field_sql_storage', 'field_test'), FALSE);
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == entity_get_info('entity_test'));

    // Disable one of those modules; entity_test should still exist.
    module_disable(array('field_test'));
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == entity_get_info('entity_test'));

    // Set the weight of a module; entity_test should still exist.
    module_set_weight('entity', -1);
    $this->assertEqual($this->container->get('module_handler')->moduleExists('entity_test'), TRUE);
    $this->assertTrue(TRUE == entity_get_info('entity_test'));

    // Reactivate the disabled module without enabling it.
    $this->enableModules(array('field_test'));

    // Create a field and an instance.
    $display = entity_create('entity_display', array(
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ));
    $field = entity_create('field_entity', array(
      'field_name' => 'test_field',
      'type' => 'test_field'
    ));
    $field->save();
    entity_create('field_instance', array(
      'field_name' => $field->id(),
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ))->save();
  }

  /**
   * Tests that theme() works right after loading a module.
   */
  function testEnableModulesTheme() {
    $original_element = $element = array(
      '#type' => 'container',
      '#markup' => 'Foo',
      '#attributes' => array(),
    );
    $this->enableModules(array('system'));
    // theme() throws an exception if modules are not loaded yet.
    $this->assertTrue(drupal_render($element));

    $element = $original_element;
    $this->disableModules(array('entity_test'));
    $this->assertTrue(drupal_render($element));
  }

}
