<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Entity\EntityApiInfoTest.
 */

namespace Drupal\system\Tests\Entity;

use Drupal\simpletest\WebTestBase;

/**
 * Tests Entity API base functionality.
 */
class EntityApiInfoTest extends WebTestBase  {

  public static function getInfo() {
    return array(
      'name' => 'Entity info',
      'description' => 'Makes sure entity info is accurately cached.',
      'group' => 'Entity API',
    );
  }

  /**
   * Ensures entity info cache is updated after changes.
   */
  function testEntityInfoChanges() {
    module_enable(array('entity_cache_test'));
    $entity_info = entity_get_info();
    $this->assertTrue(isset($entity_info['entity_cache_test']), 'Test entity type found.');

    // Change the label of the test entity type and make sure changes appear
    // after flushing caches.
    \Drupal::state()->set('entity_cache_test.label', 'New label.');
    $info = entity_get_info('entity_cache_test');
    $this->assertEqual($info['label'], 'Entity Cache Test', 'Original label appears in cached entity info.');
    $this->resetAll();
    $info = entity_get_info('entity_cache_test');
    $this->assertEqual($info['label'], 'New label.', 'New label appears in entity info.');

    // Disable the providing module and make sure the entity type is gone.
    module_disable(array('entity_cache_test', 'entity_cache_test_dependency'));
    $entity_info = entity_get_info();
    $this->assertFalse(isset($entity_info['entity_cache_test']), 'Entity type of the providing module is gone.');
  }

  /**
   * Tests entity info cache after enabling a module with a dependency on an entity providing module.
   *
   * @see entity_cache_test_watchdog()
   */
  function testEntityInfoCacheWatchdog() {
    module_enable(array('entity_cache_test'));
    $info = \Drupal::state()->get('entity_cache_test');
    $this->assertEqual($info['label'], 'Entity Cache Test', 'Entity info label is correct.');
    $this->assertEqual($info['controllers']['storage'], 'Drupal\Core\Entity\DatabaseStorageController', 'Entity controller class info is correct.');
  }
}
