<?php

/**
 * @file
 * Tests for Block module regarding hook_entity_operations_alter().
 */

namespace Drupal\block\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\simpletest\WebTestBase;

/**
 * Functional tests for the hook_entity_operations_alter().
 */
class BlockHookOperationTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('block', 'entity_test');

  public static function getInfo() {
    return array(
      'name' => 'Block operations hook',
      'description' => 'Implement hook entity operations alter.',
      'group' => 'Block',
    );
  }

  public function setUp() {
    parent::setUp();

    $permissions = array(
      'administer blocks',
    );

    // Create and log in user.
    $admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin_user);
  }

  /*
   * Tests the block list to see if the test_operation link is added.
   */
  public function testBlockOperationAlter() {
    // Add a test block, any block will do.
    // Set the machine name so the test_operation link can be built later.
    $block_machine_name = Unicode::strtolower($this->randomName(16));
    $this->drupalPlaceBlock('system_powered_by_block', array('machine_name' => $block_machine_name));

    // Get the Block listing.
    $this->drupalGet('admin/structure/block');

    $test_operation_link = 'admin/structure/block/manage/stark.' . $block_machine_name . '/test_operation';
    // Test if the test_operation link is on the page.
    $this->assertLinkByHref($test_operation_link);
  }

}
