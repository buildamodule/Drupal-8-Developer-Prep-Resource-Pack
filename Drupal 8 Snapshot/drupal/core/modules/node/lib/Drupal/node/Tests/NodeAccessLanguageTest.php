<?php

/**
 * @file
 * Definition of Drupal\node\Tests\NodeAccessLanguageTest.
 */

namespace Drupal\node\Tests;

use Drupal\Core\Language\Language;

/**
 * Verifies node_access() functionality for multiple languages.
 */
class NodeAccessLanguageTest extends NodeTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('language', 'node_access_test');

  public static function getInfo() {
    return array(
      'name' => 'Node access language',
      'description' => 'Test node_access and db_select() with node_access tag functionality with multiple languages with a test node access module that is not language-aware.',
      'group' => 'Node',
    );
  }

  function setUp() {
    parent::setUp();

    // After enabling a node access module, the access table has to be rebuild.
    node_access_rebuild();

    // Enable the private node feature of the node_access_test module.
    \Drupal::state()->set('node_access_test.private', TRUE);

    // Add Hungarian and Catalan.
    $language = new Language(array(
      'id' => 'hu',
    ));
    language_save($language);
    $language = new Language(array(
      'id' => 'ca',
    ));
    language_save($language);
  }

  /**
   * Tests node_access() with multiple node languages and no private nodes.
   */
  function testNodeAccess() {
    $web_user = $this->drupalCreateUser(array('access content'));

    $expected_node_access = array('view' => TRUE, 'update' => FALSE, 'delete' => FALSE);
    $expected_node_access_no_access = array('view' => FALSE, 'update' => FALSE, 'delete' => FALSE);

    // Creating a public node with langcode Hungarian, will be saved as the
    // fallback in node access table.
    $node_public = $this->drupalCreateNode(array('body' => array(array()), 'langcode' => 'hu', 'private' => FALSE));
    $this->assertTrue($node_public->langcode == 'hu', 'Node created as Hungarian.');

    // Tests the default access is provided for the public Hungarian node.
    $this->assertNodeAccess($expected_node_access, $node_public, $web_user);

    // Tests that Hungarian provided specifically results in the same.
    $this->assertNodeAccess($expected_node_access, $node_public, $web_user, 'hu');

    // There is no specific Catalan version of this node and Croatian is not
    // even set up on the system in this scenario, so the user will not get
    // access to these nodes.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public, $web_user, 'ca');
    $this->assertNodeAccess($expected_node_access_no_access, $node_public, $web_user, 'hr');

    // Creating a public node with no special langcode, like when no language
    // module enabled.
    $node_public_no_language = $this->drupalCreateNode(array('private' => FALSE));
    $this->assertTrue($node_public_no_language->langcode == Language::LANGCODE_NOT_SPECIFIED, 'Node created with not specified language.');

    // Tests that access is granted if requested with no language.
    $this->assertNodeAccess($expected_node_access, $node_public_no_language, $web_user);

    // Tests that access is not granted if requested with Hungarian language.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public_no_language, $web_user, 'hu');

    // There is no specific Catalan version of this node and Croatian is not
    // even set up on the system in this scenario, so the user will not get
    // access to these nodes.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public_no_language, $web_user, 'ca');
    $this->assertNodeAccess($expected_node_access_no_access, $node_public_no_language, $web_user, 'hr');

    // Reset the node access cache and turn on our test node_access() code.
    drupal_static_reset('node_access');
    variable_set('node_access_test_secret_catalan', 1);

    // Tests that access is granted if requested with no language.
    $this->assertNodeAccess($expected_node_access, $node_public_no_language, $web_user);

    // Tests that Hungarian is still not accessible.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public_no_language, $web_user, 'hu');

    // Tests that Catalan is still not accessible.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public_no_language, $web_user, 'ca');
  }

  /**
   * Tests node_access() with multiple node languages and private nodes.
   */
  function testNodeAccessPrivate() {
    $web_user = $this->drupalCreateUser(array('access content'));

    $node = $this->drupalCreateNode(array('body' => array(array()), 'langcode' => 'hu'));
    $this->assertTrue($node->langcode == 'hu', 'Node created as Hungarian.');
    $expected_node_access = array('view' => TRUE, 'update' => FALSE, 'delete' => FALSE);
    $expected_node_access_no_access = array('view' => FALSE, 'update' => FALSE, 'delete' => FALSE);

    // Creating a private node with langcode Hungarian, will be saved as the
    // fallback in node access table.
    $node_public = $this->drupalCreateNode(array('body' => array(array()), 'langcode' => 'hu', 'private' => TRUE));
    $this->assertTrue($node_public->langcode == 'hu', 'Node created as Hungarian.');

    // Tests the default access is not provided for the private Hungarian node.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public, $web_user);

    // Tests that Hungarian provided specifically results in the same.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public, $web_user, 'hu');

    // There is no specific Catalan version of this node and Croatian is not
    // even set up on the system in this scenario, so the user will not get
    // access to these nodes.
    $this->assertNodeAccess($expected_node_access_no_access, $node_public, $web_user, 'ca');
    $this->assertNodeAccess($expected_node_access_no_access, $node_public, $web_user, 'hr');

    // Creating a private node with no special langcode, like when no language
    // module enabled.
    $node_private_no_language = $this->drupalCreateNode(array('private' => TRUE));
    $this->assertTrue($node_private_no_language->langcode == Language::LANGCODE_NOT_SPECIFIED, 'Node created with not specified language.');

    // Tests that access is not granted if requested with no language.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_no_language, $web_user);

    // Tests that access is not granted if requested with Hungarian language.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_no_language, $web_user, 'hu');

    // There is no specific Catalan version of this node and Croatian is not
    // even set up on the system in this scenario, so the user will not get
    // access to these nodes.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_no_language, $web_user, 'ca');
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_no_language, $web_user, 'hr');

    // Reset the node access cache and turn on our test node_access() code.
    entity_access_controller('node')->resetCache();
    \Drupal::state()->set('node_access_test_secret_catalan', 1);

    // Tests that access is not granted if requested with no language.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_no_language, $web_user);

    // Tests that Hungarian is still not accessible.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_no_language, $web_user, 'hu');

    // Tests that Catalan is still not accessible.
    $this->assertNodeAccess($expected_node_access_no_access, $node_private_no_language, $web_user, 'ca');
  }

  /**
   * Tests db_select() with a 'node_access' tag and langcode metadata.
   */
  function testNodeAccessQueryTag() {
    // Create a normal authenticated user.
    $web_user = $this->drupalCreateUser(array('access content'));

    // Load the user 1 user for later use as an admin user with permission to
    // see everything.
    $admin_user = user_load(1);

    // Creating a private node with langcode Hungarian, will be saved as
    // the fallback in node access table.
    $node_private = $this->drupalCreateNode(array('body' => array(array()), 'langcode' => 'hu', 'private' => TRUE));
    $this->assertTrue($node_private->langcode == 'hu', 'Node created as Hungarian.');

    // Creating a public node with langcode Hungarian, will be saved as
    // the fallback in node access table.
    $node_public = $this->drupalCreateNode(array('body' => array(array()), 'langcode' => 'hu', 'private' => FALSE));
    $this->assertTrue($node_public->langcode == 'hu', 'Node created as Hungarian.');

    // Creating a public node with no special langcode, like when no language
    // module enabled.
    $node_no_language = $this->drupalCreateNode(array('private' => FALSE));
    $this->assertTrue($node_no_language->langcode == Language::LANGCODE_NOT_SPECIFIED, 'Node created with not specified language.');

    // Query the nodes table as the web user with the node access tag and no
    // specific langcode.
    $select = db_select('node', 'n')
    ->fields('n', array('nid'))
    ->addMetaData('account', $web_user)
    ->addTag('node_access');
    $nids = $select->execute()->fetchAllAssoc('nid');

    // The public node and no language node should be returned. Because no
    // langcode is given it will use the fallback node.
    $this->assertEqual(count($nids), 2, 'db_select() returns 2 node');
    $this->assertTrue(array_key_exists($node_public->id(), $nids), 'Returned node ID is public node.');
    $this->assertTrue(array_key_exists($node_no_language->id(), $nids), 'Returned node ID is no language node.');

    // Query the nodes table as the web user with the node access tag and
    // langcode de.
    $select = db_select('node', 'n')
    ->fields('n', array('nid'))
    ->addMetaData('account', $web_user)
    ->addMetaData('langcode', 'de')
    ->addTag('node_access');
    $nids = $select->execute()->fetchAllAssoc('nid');

    // Because no nodes are created in German, no nodes are returned.
    $this->assertTrue(empty($nids), 'db_select() returns an empty result.');

    // Query the nodes table as admin user (full access) with the node access
    // tag and no specific langcode.
    $select = db_select('node', 'n')
    ->fields('n', array('nid'))
    ->addMetaData('account', $admin_user)
    ->addTag('node_access');
    $nids = $select->execute()->fetchAllAssoc('nid');

    // All nodes are returned.
    $this->assertEqual(count($nids), 3, 'db_select() returns all three nodes.');

    // Query the nodes table as admin user (full access) with the node access
    // tag and langcode de.
    $select = db_select('node', 'n')
    ->fields('n', array('nid'))
    ->addMetaData('account', $admin_user)
    ->addMetaData('langcode', 'de')
    ->addTag('node_access');
    $nids = $select->execute()->fetchAllAssoc('nid');

    // All nodes are returned because node access tag is not invoked when the
    // user is user 1.
    $this->assertEqual(count($nids), 3, 'db_select() returns all three nodes.');
  }

}
