<?php

/**
 * @file
 * Contains \Drupal\Tests\UnitTestCase.
 */

namespace Drupal\Tests;

use Drupal\Component\Utility\Random;

/**
 * Provides a base class and helpers for Drupal unit tests.
 */
abstract class UnitTestCase extends \PHPUnit_Framework_TestCase {

  /**
   * This method exists to support the simpletest UI runner.
   *
   * It should eventually be replaced with something native to phpunit.
   *
   * Also, this method is empty because you can't have an abstract static
   * method. Sub-classes should always override it.
   *
   * @return array
   *   An array describing the test like so:
   *   array(
   *     'name' => 'Something Test',
   *     'description' => 'Tests Something',
   *     'group' => 'Something',
   *   )
   */
  public static function getInfo() {
    throw new \RuntimeException("Sub-class must implement the getInfo method!");
  }

  /**
   * Generates a unique random string containing letters and numbers.
   *
   * @param int $length
   *   Length of random string to generate.
   *
   * @return string
   *   Randomly generated unique string.
   *
   * @see \Drupal\Component\Utility::string()
   */
  public function randomName($length = 8) {
    return Random::name($length, TRUE);
  }

  /**
   * Returns a stub config factory that behaves according to the passed in array.
   *
   * Use this to generate a config factory that will return the desired values
   * for the given config names.
   *
   * @param array $configs
   *   An associative array of configuration settings whose keys are configuration
   *   object names and whose values are key => value arrays for the configuration
   *   object in question.
   *
   * @return \PHPUnit_Framework_MockObject_MockBuilder
   *   A MockBuilder object for the ConfigFactory with the desired return values.
   */
  public function getConfigFactoryStub($configs) {
    $config_map = array();
    // Construct the desired configuration object stubs, each with its own
    // desired return map.
    foreach ($configs as $config_name => $config_values) {
      $config_object = $this->getMockBuilder('Drupal\Core\Config\Config')
        ->disableOriginalConstructor()
        ->getMock();
      $map = array();
      foreach ($config_values as $key => $value) {
        $map[] = array($key, $value);
      }
      // Also allow to pass in no argument.
      $map[] = array('', $config_values);

      $config_object->expects($this->any())
        ->method('get')
        ->will($this->returnValueMap($map));

      $config_map[] = array($config_name, $config_object);
    }
    // Construct a config factory with the array of configuration object stubs
    // as its return map.
    $config_factory = $this->getMockBuilder('Drupal\Core\Config\ConfigFactory')
      ->disableOriginalConstructor()
      ->getMock();
    $config_factory->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap($config_map));
    return $config_factory;
  }

  /**
   * Returns a stub config storage that returns the supplied configuration.
   *
   * @param array $configs
   *   An associative array of configuration settings whose keys are
   *   configuration object names and whose values are key => value arrays
   *   for the configuration object in question.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   A mocked config storage.
   */
  public function getConfigStorageStub(array $configs) {
    $config_storage = $this->getMock('Drupal\Core\Config\NullStorage');
    $config_storage->expects($this->any())
      ->method('listAll')
      ->will($this->returnValue(array_keys($configs)));

    foreach ($configs as $name => $config) {
      $config_storage->expects($this->any())
        ->method('read')
        ->with($this->equalTo($name))
        ->will($this->returnValue($config));
    }
    return $config_storage;
  }

  /**
   * Mocks a block with a block plugin.
   *
   * @param string $machine_name
   *   The machine name of the block plugin.
   *
   * @return \Drupal\block\BlockInterface|\PHPUnit_Framework_MockObject_MockObject
   *   The mocked block.
   */
  protected function getBlockMockWithMachineName($machine_name) {
    $plugin = $this->getMockBuilder('Drupal\block\BlockBase')
      ->disableOriginalConstructor()
      ->getMock();
    $plugin->expects($this->any())
      ->method('getMachineNameSuggestion')
      ->will($this->returnValue($machine_name));

    $block = $this->getMockBuilder('Drupal\block\Plugin\Core\Entity\Block')
      ->disableOriginalConstructor()
      ->getMock();
    $block->expects($this->any())
      ->method('getPlugin')
      ->will($this->returnValue($plugin));
    return $block;
  }

}
