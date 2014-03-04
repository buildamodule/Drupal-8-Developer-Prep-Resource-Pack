<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Update\DependencyOrderingTest.
 */

namespace Drupal\system\Tests\Update;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the update dependency ordering system.
 */
class DependencyOrderingTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('update_test_1', 'update_test_2', 'update_test_3');

  public static function getInfo() {
    return array(
      'name' => 'Update dependency ordering',
      'description' => 'Test that update functions are run in the proper order.',
      'group' => 'Update API',
    );
  }

  function setUp() {
    parent::setUp();
    require_once DRUPAL_ROOT . '/core/includes/update.inc';
  }

  /**
   * Test that updates within a single module run in the correct order.
   */
  function testUpdateOrderingSingleModule() {
    $starting_updates = array(
      'update_test_1' => 8000,
    );
    $expected_updates = array(
      'update_test_1_update_8000',
      'update_test_1_update_8001',
      'update_test_1_update_8002',
    );
    $actual_updates = array_keys(update_resolve_dependencies($starting_updates));
    $this->assertEqual($expected_updates, $actual_updates, 'Updates within a single module run in the correct order.');
  }

  /**
   * Test that dependencies between modules are resolved correctly.
   */
  function testUpdateOrderingModuleInterdependency() {
    $starting_updates = array(
      'update_test_2' => 8000,
      'update_test_3' => 8000,
    );
    $update_order = array_keys(update_resolve_dependencies($starting_updates));
    // Make sure that each dependency is satisfied.
    $first_dependency_satisfied = array_search('update_test_2_update_8000', $update_order) < array_search('update_test_3_update_8000', $update_order);
    $this->assertTrue($first_dependency_satisfied, 'The dependency of the second module on the first module is respected by the update function order.');
    $second_dependency_satisfied = array_search('update_test_3_update_8000', $update_order) < array_search('update_test_2_update_8001', $update_order);
    $this->assertTrue($second_dependency_satisfied, 'The dependency of the first module on the second module is respected by the update function order.');
  }
}
