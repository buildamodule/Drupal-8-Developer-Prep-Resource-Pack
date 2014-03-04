<?php

/**
 * @file
 * Definition of Drupal\node\Tests\NodeBlockFunctionalTest.
 */

namespace Drupal\node\Tests;

/**
 * Functional tests for the node module blocks.
 */
class NodeBlockFunctionalTest extends NodeTestBase {

  /**
   * An administrative user for testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * An unprivileged user for testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('block');

  public static function getInfo() {
    return array(
      'name' => 'Node blocks',
      'description' => 'Test node block functionality.',
      'group' => 'Node',
    );
  }

  function setUp() {
    parent::setUp();

    // Create users and test node.
    $this->adminUser = $this->drupalCreateUser(array('administer content types', 'administer nodes', 'administer blocks'));
    $this->webUser = $this->drupalCreateUser(array('access content', 'create article content'));
  }

  /**
   * Tests the recent comments block.
   */
  public function testRecentNodeBlock() {
    $this->drupalLogin($this->adminUser);

    // Disallow anonymous users to view content.
    user_role_change_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access content' => FALSE,
    ));

    // Enable the recent content block with two items.
    $block = $this->drupalPlaceBlock('node_recent_block', array('machine_name' => 'test_block', 'block_count' => 2));

    // Test that block is not visible without nodes.
    $this->drupalGet('');
    $this->assertText(t('No content available.'), 'Block with "No content available." found.');

    // Add some test nodes.
    $default_settings = array('uid' => $this->webUser->id(), 'type' => 'article');
    $node1 = $this->drupalCreateNode($default_settings);
    $node2 = $this->drupalCreateNode($default_settings);
    $node3 = $this->drupalCreateNode($default_settings);

    // Change the changed time for node so that we can test ordering.
    db_update('node_field_data')
      ->fields(array(
        'changed' => $node1->changed + 100,
      ))
      ->condition('nid', $node2->id())
      ->execute();
    db_update('node_field_data')
      ->fields(array(
        'changed' => $node1->changed + 200,
      ))
      ->condition('nid', $node3->id())
      ->execute();

    // Test that a user without the 'access content' permission cannot
    // see the block.
    $this->drupalLogout();
    $this->drupalGet('');
    $this->assertNoText($block->label(), 'Block was not found.');

    // Test that only the 2 latest nodes are shown.
    $this->drupalLogin($this->webUser);
    $this->assertNoText($node1->label(), 'Node not found in block.');
    $this->assertText($node2->label(), 'Node found in block.');
    $this->assertText($node3->label(), 'Node found in block.');

    // Check to make sure nodes are in the right order.
    $this->assertTrue($this->xpath('//div[@id="block-test-block"]/div/table/tbody/tr[position() = 1]/td/div/a[text() = "' . $node3->label() . '"]'), 'Nodes were ordered correctly in block.');

    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);

    // Set the number of recent nodes to show to 10.
    $block->getPlugin()->setConfigurationValue('block_count', 10);
    $block->save();

    // Post an additional node.
    $node4 = $this->drupalCreateNode($default_settings);
    // drupalCreateNode() does not automatically flush content caches unlike
    // posting a node from a node form.
    cache_invalidate_tags(array('content' => TRUE));

    // Test that all four nodes are shown.
    $this->drupalGet('');
    $this->assertText($node1->label(), 'Node found in block.');
    $this->assertText($node2->label(), 'Node found in block.');
    $this->assertText($node3->label(), 'Node found in block.');
    $this->assertText($node4->label(), 'Node found in block.');

    // Enable the "Powered by Drupal" block only on article nodes.
    $block = $this->drupalPlaceBlock('system_powered_by_block', array(
      'visibility' => array(
        'node_type' => array(
          'types' => array(
            'article' => 'article',
          ),
        ),
      ),
    ));
    $visibility = $block->get('visibility');
    $this->assertTrue(isset($visibility['node_type']['types']['article']), 'Visibility settings were saved to configuration');

    // Create a page node.
    $node5 = $this->drupalCreateNode(array('uid' => $this->adminUser->id(), 'type' => 'page'));

    // Verify visibility rules.
    $this->drupalGet('');
    $label = $block->label();
    $this->assertNoText($label, 'Block was not displayed on the front page.');
    $this->drupalGet('node/add/article');
    $this->assertText($label, 'Block was displayed on the node/add/article page.');
    $this->drupalGet('node/' . $node1->id());
    $this->assertText($label, 'Block was displayed on the node/N when node is of type article.');
    $this->drupalGet('node/' . $node5->id());
    $this->assertNoText($label, 'Block was not displayed on nodes of type page.');
  }

}
