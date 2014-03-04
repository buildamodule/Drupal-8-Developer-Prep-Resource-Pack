<?php

/**
 * @file
 * Contains \Drupal\config\Tests\ConfigEntityStatusUITest.
 */

namespace Drupal\config\Tests;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\simpletest\WebTestBase;

/**
 * Tests configuration entity status UI functionality.
 */
class ConfigEntityStatusUITest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('config_test');

  public static function getInfo() {
    return array(
      'name' => 'Configuration entity status UI',
      'description' => 'Tests configuration entity status UI functionality.',
      'group' => 'Configuration',
    );
  }

  /**
   * Tests status operations.
   */
  function testCRUD() {
    $id = strtolower($this->randomName());
    $edit = array(
      'id' => $id,
      'label' => $this->randomName(),
    );
    $this->drupalPost('admin/structure/config_test/add', $edit, 'Save');

    $uri = entity_load('config_test', $id)->uri();

    // Disable an entity.
    $disable_path = "{$uri['path']}/disable";
    $this->assertLinkByHref($disable_path);
    $this->drupalGet($disable_path);
    $this->assertResponse(200);
    $this->assertNoLinkByHref($disable_path);

    // Enable an entity.
    $enable_path = "{$uri['path']}/enable";
    $this->assertLinkByHref($enable_path);
    $this->drupalGet($enable_path);
    $this->assertResponse(200);
    $this->assertNoLinkByHref($enable_path);
  }

}
