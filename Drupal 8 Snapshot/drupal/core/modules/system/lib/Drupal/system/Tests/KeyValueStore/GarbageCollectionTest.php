<?php

/**
 * @file
 * Contains Drupal\system\Tests\KeyValueStore\GarbageCollectionTest.
 */

namespace Drupal\system\Tests\KeyValueStore;

use Drupal\Core\Database\Database;
use Drupal\Core\KeyValueStore\DatabaseStorageExpirable;
use Drupal\simpletest\UnitTestBase;

/**
 * Tests garbage collection for DatabaseStorageExpirable.
 */
class GarbageCollectionTest extends UnitTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Garbage collection',
      'description' => 'Tests garbage collection for the the expirable key-value database storage.',
      'group' => 'Key-value store',
    );
  }

  protected function setUp() {
    parent::setUp();
    module_load_install('system');
    $schema = system_schema();
    db_create_table('key_value_expire', $schema['key_value_expire']);
  }

  protected function tearDown() {
    db_drop_table('key_value_expire');
    parent::tearDown();
  }

  /**
   * Tests garbage collection.
   */
  public function testGarbageCollection() {
    $collection = $this->randomName();
    $store = new DatabaseStorageExpirable($collection, Database::getConnection());

    // Insert some items and confirm that they're set.
    for ($i = 0; $i <= 3; $i++) {
      $store->setWithExpire('key_' . $i, $this->randomObject(), rand(500, 100000));
    }
    $this->assertIdentical(sizeof($store->getAll()), 4, 'Four items were written to the storage.');

    // Manually expire the data.
    for ($i = 0; $i <= 3; $i++) {
      db_merge('key_value_expire')
        ->key(array(
            'name' => 'key_' . $i,
            'collection' => $collection,
          ))
        ->fields(array(
            'expire' => REQUEST_TIME - 1,
          ))
        ->execute();
    }

    // Perform a new set operation and then manually destruct the object to
    // trigger garbage collection.
    $store->setWithExpire('autumn', 'winter', rand(500, 1000000));
    $store->destruct();

    // Query the database and confirm that the stale records were deleted.
    $result = db_query(
      'SELECT name, value FROM {key_value_expire} WHERE collection = :collection',
      array(
        ':collection' => $collection,
      ))->fetchAll();
    $this->assertIdentical(sizeof($result), 1, 'Only one item remains after garbage collection');

  }

}
