<?php

/**
 * @file
 * Contains \Drupal\node\Tests\Config\NodeImportCreateTest.
 */

namespace Drupal\node\Tests\Config;

use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests content types as part of config import.
 */
class NodeImportCreateTest extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'entity', 'field', 'text', 'field_sql_storage', 'system');

  /**
   * Set the default field storage backend for fields created during tests.
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('system', array('config_snapshot'));

    // Set default storage backend.
    $this->installConfig(array('field'));
  }

  public static function getInfo() {
    return array(
      'name' => 'Node config create tests',
      'description' => 'Create content types during config create method invocation.',
      'group' => 'Node',
    );
  }

  /**
   * Tests creating a content type during default config import.
   */
  public function testImportCreateDefault() {
    $node_type_id = 'default';

    // Check that the content type does not exist yet.
    $this->assertFalse(entity_load('node_type', $node_type_id));

    // Enable node_test_config module and check that the content type
    // shipped in the module's default config is created.
    $this->container->get('module_handler')->enable(array('node_test_config'));
    $node_type = entity_load('node_type', $node_type_id);
    $this->assertTrue($node_type, 'The default content type was created.');
  }

  /**
   * Tests creating a content type during config import.
   */
  public function testImportCreate() {
    $node_type_id = 'import';
    $node_type_config_name = "node.type.$node_type_id";

    // Simulate config data to import.
    $active = $this->container->get('config.storage');
    $staging = $this->container->get('config.storage.staging');
    $this->copyConfig($active, $staging);
    // Manually add new node type.
    $src_dir = drupal_get_path('module', 'node_test_config') . '/staging';
    $this->assertTrue(file_unmanaged_copy("$src_dir/$node_type_config_name.yml", "public://config_staging/$node_type_config_name.yml"));

    // Import the content of the staging directory.
    $this->configImporter()->import();

    // Check that the content type was created.
    $node_type = entity_load('node_type', $node_type_id);
    $this->assertTrue($node_type, 'Import node type from staging was created.');
  }

}
