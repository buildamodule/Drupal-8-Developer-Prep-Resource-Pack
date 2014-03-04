<?php

/**
 * @file
 * Contains \Drupal\Tests\Component\Discovery\YamlDiscoveryTest.
 */

namespace Drupal\Tests\Component\Discovery;

use Drupal\Tests\UnitTestCase;
use Drupal\Component\Discovery\YamlDiscovery;

/**
 * Tests the YamlDiscovery component class.
 */
class YamlDiscoveryTest extends UnitTestCase {

  public static function getInfo() {
    return array(
      'name' => 'YamlDiscovery',
      'description' => 'YamlDiscovery component unit tests.',
      'group' => 'Discovery',
    );
  }

  /**
   * Tests the YAML file discovery.
   */
  public function testDiscovery() {
    $base_path = __DIR__ . '/Fixtures';
    // Set up the directories to search.
    $directories = array(
      'test_1' => $base_path . '/test_1',
      'test_2' => $base_path . '/test_2',
    );

    $discovery = new YamlDiscovery('test', $directories);
    $data = $discovery->findAll();

    $this->assertEquals(count($data), 2);
    $this->assertArrayHasKey('test_1', $data);
    $this->assertArrayHasKey('test_2', $data);

    foreach ($data as $item) {
      $this->assertArrayHasKey('name', $item);
      $this->assertEquals($item['name'], 'test');
    }
  }

}
