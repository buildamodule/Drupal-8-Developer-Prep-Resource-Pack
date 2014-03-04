<?php

/**
 * @file
 * Contains \Drupal\field\Tests\FieldImportChangeTest.
 */

namespace Drupal\field\Tests;

/**
 * Tests updating fields and instances as part of config import.
 */
class FieldImportChangeTest extends FieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('field_test_config');

  public static function getInfo() {
    return array(
      'name' => 'Field config change tests',
      'description' => 'Update field and instances during config change method invocation.',
      'group' => 'Field API',
    );
  }

  /**
   * Tests importing an updated field instance.
   */
  function testImportChange() {
    $field_id = 'field_test_import';
    $instance_id = "entity_test.entity_test.$field_id";
    $instance_config_name = "field.instance.$instance_id";

    // Import default config.
    $this->installConfig(array('field_test_config'));
    $active = $this->container->get('config.storage');
    $staging = $this->container->get('config.storage.staging');
    $this->copyConfig($active, $staging);

    // Save as files in the the staging directory.
    $instance = $active->read($instance_config_name);
    $new_label = 'Test update import field';
    $instance['label'] = $new_label;
    $staging->write($instance_config_name, $instance);

    // Import the content of the staging directory.
    $this->configImporter()->import();

    // Check that the updated config was correctly imported.
    $instance = entity_load('field_instance', $instance_id);
    $this->assertEqual($instance['label'], $new_label, 'Instance label updated');
  }
}

