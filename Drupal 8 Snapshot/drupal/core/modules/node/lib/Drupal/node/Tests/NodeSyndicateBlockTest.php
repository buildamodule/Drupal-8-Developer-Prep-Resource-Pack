<?php

/**
 * @file
 * Definition of Drupal\node\Tests\NodeSyndicateBlockTest.
 */

namespace Drupal\node\Tests;

/**
 * Tests the availability of the syndicate block.
 */
class NodeSyndicateBlockTest extends NodeTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('block');

  public static function getInfo() {
    return array(
      'name' => 'Syndicate block',
      'description' => 'Check if the syndicate block is available.',
      'group' => 'Node',
    );
  }

  function setUp() {
    parent::setUp();

    // Create a user and log in.
    $admin_user = $this->drupalCreateUser(array('administer blocks'));
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that the "Syndicate" block is shown when enabled.
   */
  public function testSyndicateBlock() {
    // Place the "Syndicate" block and confirm that it is rendered.
    $this->drupalPlaceBlock('node_syndicate_block', array('machine_name' => 'test_syndicate_block'));
    $this->drupalGet('');
    $this->assertFieldByXPath('//div[@id="block-test-syndicate-block"]/*', NULL, 'Syndicate block found.');
  }

}
