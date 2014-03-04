<?php

/**
 * @file
 * Contains Drupal\config\Tests\ConfigEntityUnitTest.
 */

namespace Drupal\config\Tests;

use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Unit tests for configuration controllers and objects.
 */
class ConfigEntityUnitTest extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('config_test');

  public static function getInfo() {
    return array(
      'name' => 'Configuration entity methods',
      'description' => 'Unit tests for configuration entity base methods.',
      'group' => 'Configuration',
    );
  }

  /**
   * Tests storage controller methods.
   */
  public function testStorageControllerMethods() {
    $controller = $this->container->get('plugin.manager.entity')->getStorageController('config_test');
    $info = entity_get_info('config_test');

    $expected = $info['config_prefix'] . '.';
    $this->assertIdentical($controller->getConfigPrefix(), $expected);

    // Test the static extractID() method.
    $expected_id = 'test_id';
    $config_name = $info['config_prefix'] . '.' . $expected_id;
    $this->assertIdentical($controller::getIDFromConfigName($config_name, $info['config_prefix']), $expected_id);

    // Create three entities, two with the same style.
    $style = $this->randomName(8);
    for ($i = 0; $i < 2; $i++) {
      $entity = $controller->create(array(
        'id' => $this->randomName(),
        'label' => $this->randomString(),
        'style' => $style,
      ));
      $entity->save();
    }
    $entity = $controller->create(array(
      'id' => $this->randomName(),
      'label' => $this->randomString(),
      // Use a different length for the entity to ensure uniqueness.
      'style' => $this->randomName(9),
    ));
    $entity->save();

    $entities = $controller->loadByProperties();
    $this->assertEqual(count($entities), 3, 'Three entities are loaded when no properties are specified.');

    $entities = $controller->loadByProperties(array('style' => $style));
    $this->assertEqual(count($entities), 2, 'Two entities are loaded when the style property is specified.');
    $this->assertEqual(reset($entities)->get('style'), $style, 'The loaded entities have the style value specified.');
  }

}
