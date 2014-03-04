<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Cache\GenericCacheBackendUnitTestBase.
 */

namespace Drupal\system\Tests\Cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\simpletest\DrupalUnitTestBase;

use stdClass;

/**
 * Tests any cache backend.
 *
 * Full generic unit test suite for any cache backend. In order to use it for a
 * cache backend implementation, extend this class and override the
 * createBackendInstace() method to return an object.
 *
 * @see DatabaseBackendUnitTestCase
 *   For a full working implementation.
 */
abstract class GenericCacheBackendUnitTestBase extends DrupalUnitTestBase {

  /**
   * Array of objects implementing Drupal\Core\Cache\CacheBackendInterface.
   *
   * @var array
   */
  protected $cachebackends;

  /**
   * Cache bin to use for testing.
   *
   * @var string
   */
  protected $testBin;

  /**
   * Random value to use in tests.
   *
   * @var string
   */
  protected $defaultValue;

  /**
   * Gets the testing bin.
   *
   * Override this method if you want to work on a different bin than the
   * default one.
   *
   * @return string
   *   Bin name.
   */
  protected function getTestBin() {
    if (!isset($this->testBin)) {
      $this->testBin = 'page';
    }
    return $this->testBin;
  }

  /**
   * Creates a cache backend to test.
   *
   * Override this method to test a CacheBackend.
   *
   * @param string $bin
   *   Bin name to use for this backend instance.
   *
   * @return Drupal\Core\Cache\CacheBackendInterface
   *   Cache backend to test.
   */
  protected abstract function createCacheBackend($bin);

  /**
   * Allows specific implementation to change the environment before a test run.
   */
  public function setUpCacheBackend() {
  }

  /**
   * Allows alteration of environment after a test run but before tear down.
   *
   * Used before the real tear down because the tear down will change things
   * such as the database prefix.
   */
  public function tearDownCacheBackend() {
  }

  /**
   * Gets a backend to test; this will get a shared instance set in the object.
   *
   * @return Drupal\Core\Cache\CacheBackendInterface
   *   Cache backend to test.
   */
  final function getCacheBackend($bin = null) {
    if (!isset($bin)) {
      $bin = $this->getTestBin();
    }
    if (!isset($this->cachebackends[$bin])) {
      $this->cachebackends[$bin] = $this->createCacheBackend($bin);
      // Ensure the backend is empty.
      $this->cachebackends[$bin]->deleteAll();
    }
    return $this->cachebackends[$bin];
  }

  public function setUp() {
    $this->cachebackends = array();
    $this->defaultValue = $this->randomName(10);

    parent::setUp();

    $this->setUpCacheBackend();
  }

  public function tearDown() {
    // Destruct the registered backend, each test will get a fresh instance,
    // properly emptying it here ensure that on persistant data backends they
    // will come up empty the next test.
    foreach ($this->cachebackends as $bin => $cachebackend) {
      $this->cachebackends[$bin]->deleteAll();
    }
    unset($this->cachebackends);

    $this->tearDownCacheBackend();

    parent::tearDown();
  }

  /**
   * Tests the get and set methods of Drupal\Core\Cache\CacheBackendInterface.
   */
  public function testSetGet() {
    $backend = $this->getCacheBackend();

    $this->assertIdentical(FALSE, $backend->get('test1'), "Backend does not contain data for cache id test1.");
    $backend->set('test1', 7);
    $cached = $backend->get('test1');
    $this->assert(is_object($cached), "Backend returned an object for cache id test1.");
    $this->assertIdentical(7, $cached->data);
    $this->assertTrue($cached->valid, 'Item is marked as valid.');
    $this->assertEqual($cached->created, REQUEST_TIME, 'Created time is correct.');
    $this->assertEqual($cached->expire, CacheBackendInterface::CACHE_PERMANENT, 'Expire time is correct.');

    $this->assertIdentical(FALSE, $backend->get('test2'), "Backend does not contain data for cache id test2.");
    $backend->set('test2', array('value' => 3), REQUEST_TIME + 3);
    $cached = $backend->get('test2');
    $this->assert(is_object($cached), "Backend returned an object for cache id test2.");
    $this->assertIdentical(array('value' => 3), $cached->data);
    $this->assertTrue($cached->valid, 'Item is marked as valid.');
    $this->assertEqual($cached->created, REQUEST_TIME, 'Created time is correct.');
    $this->assertEqual($cached->expire, REQUEST_TIME + 3, 'Expire time is correct.');

    $backend->set('test3', 'foobar', REQUEST_TIME - 3);
    $this->assertFalse($backend->get('test3'), 'Invalid item not returned.');
    $cached = $backend->get('test3', TRUE);
    $this->assert(is_object($cached), 'Backend returned an object for cache id test3.');
    $this->assertFalse($cached->valid, 'Item is marked as valid.');
    $this->assertEqual($cached->created, REQUEST_TIME, 'Created time is correct.');
    $this->assertEqual($cached->expire, REQUEST_TIME - 3, 'Expire time is correct.');
  }

  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::delete().
   */
  public function testDelete() {
    $backend = $this->getCacheBackend();

    $this->assertIdentical(FALSE, $backend->get('test1'), "Backend does not contain data for cache id test1.");
    $backend->set('test1', 7);
    $this->assert(is_object($backend->get('test1')), "Backend returned an object for cache id test1.");

    $this->assertIdentical(FALSE, $backend->get('test2'), "Backend does not contain data for cache id test2.");
    $backend->set('test2', 3);
    $this->assert(is_object($backend->get('test2')), "Backend returned an object for cache id %cid.");

    $backend->delete('test1');
    $this->assertIdentical(FALSE, $backend->get('test1'), "Backend does not contain data for cache id test1 after deletion.");

    $cached = $backend->get('test2');
    $this->assert(is_object($backend->get('test2')), "Backend still has an object for cache id test2.");

    $backend->delete('test2');
    $this->assertIdentical(FALSE, $backend->get('test2'), "Backend does not contain data for cache id test2 after deletion.");
  }

  /**
   * Tests data type preservation.
   */
  public function testValueTypeIsKept() {
    $backend = $this->getCacheBackend();

    $variables = array(
      'test1' => 1,
      'test2' => '0',
      'test3' => '',
      'test4' => 12.64,
      'test5' => FALSE,
      'test6' => array(1,2,3),
    );

    // Create cache entries.
    foreach ($variables as $cid => $data) {
      $backend->set($cid, $data);
    }

    // Retrieve and test cache objects.
    foreach ($variables as $cid => $value) {
      $object = $backend->get($cid);
      $this->assert(is_object($object), sprintf("Backend returned an object for cache id %s.", $cid));
      $this->assertIdentical($value, $object->data, sprintf("Data of cached id %s kept is identical in type and value", $cid));
    }
  }

  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::getMultiple().
   */
  public function testGetMultiple() {
    $backend = $this->getCacheBackend();

    // Set numerous testing keys.
    $backend->set('test1', 1);
    $backend->set('test2', 3);
    $backend->set('test3', 5);
    $backend->set('test4', 7);
    $backend->set('test5', 11);
    $backend->set('test6', 13);
    $backend->set('test7', 17);

    // Mismatch order for harder testing.
    $reference = array(
      'test3',
      'test7',
      'test21', // Cid does not exist.
      'test6',
      'test19', // Cid does not exist until added before second getMultiple().
      'test2',
    );

    $cids = $reference;
    $ret = $backend->getMultiple($cids);
    // Test return - ensure it contains existing cache ids.
    $this->assert(isset($ret['test2']), "Existing cache id test2 is set.");
    $this->assert(isset($ret['test3']), "Existing cache id test3 is set.");
    $this->assert(isset($ret['test6']), "Existing cache id test6 is set.");
    $this->assert(isset($ret['test7']), "Existing cache id test7 is set.");
    // Test return - ensure that objects has expected properties.
    $this->assertTrue($ret['test2']->valid, 'Item is marked as valid.');
    $this->assertEqual($ret['test2']->created, REQUEST_TIME, 'Created time is correct.');
    $this->assertEqual($ret['test2']->expire, CacheBackendInterface::CACHE_PERMANENT, 'Expire time is correct.');
    // Test return - ensure it does not contain nonexistent cache ids.
    $this->assertFalse(isset($ret['test19']), "Nonexistent cache id test19 is not set.");
    $this->assertFalse(isset($ret['test21']), "Nonexistent cache id test21 is not set.");
    // Test values.
    $this->assertIdentical($ret['test2']->data, 3, "Existing cache id test2 has the correct value.");
    $this->assertIdentical($ret['test3']->data, 5, "Existing cache id test3 has the correct value.");
    $this->assertIdentical($ret['test6']->data, 13, "Existing cache id test6 has the correct value.");
    $this->assertIdentical($ret['test7']->data, 17, "Existing cache id test7 has the correct value.");
    // Test $cids array - ensure it contains cache id's that do not exist.
    $this->assert(in_array('test19', $cids), "Nonexistent cache id test19 is in cids array.");
    $this->assert(in_array('test21', $cids), "Nonexistent cache id test21 is in cids array.");
    // Test $cids array - ensure it does not contain cache id's that exist.
    $this->assertFalse(in_array('test2', $cids), "Existing cache id test2 is not in cids array.");
    $this->assertFalse(in_array('test3', $cids), "Existing cache id test3 is not in cids array.");
    $this->assertFalse(in_array('test6', $cids), "Existing cache id test6 is not in cids array.");
    $this->assertFalse(in_array('test7', $cids), "Existing cache id test7 is not in cids array.");

    // Test a second time after deleting and setting new keys which ensures that
    // if the backend uses statics it does not cause unexpected results.
    $backend->delete('test3');
    $backend->delete('test6');
    $backend->set('test19', 57);

    $cids = $reference;
    $ret = $backend->getMultiple($cids);
    // Test return - ensure it contains existing cache ids.
    $this->assert(isset($ret['test2']), "Existing cache id test2 is set");
    $this->assert(isset($ret['test7']), "Existing cache id test7 is set");
    $this->assert(isset($ret['test19']), "Added cache id test19 is set");
    // Test return - ensure it does not contain nonexistent cache ids.
    $this->assertFalse(isset($ret['test3']), "Deleted cache id test3 is not set");
    $this->assertFalse(isset($ret['test6']), "Deleted cache id test6 is not set");
    $this->assertFalse(isset($ret['test21']), "Nonexistent cache id test21 is not set");
    // Test values.
    $this->assertIdentical($ret['test2']->data, 3, "Existing cache id test2 has the correct value.");
    $this->assertIdentical($ret['test7']->data, 17, "Existing cache id test7 has the correct value.");
    $this->assertIdentical($ret['test19']->data, 57, "Added cache id test19 has the correct value.");
    // Test $cids array - ensure it contains cache id's that do not exist.
    $this->assert(in_array('test3', $cids), "Deleted cache id test3 is in cids array.");
    $this->assert(in_array('test6', $cids), "Deleted cache id test6 is in cids array.");
    $this->assert(in_array('test21', $cids), "Nonexistent cache id test21 is in cids array.");
    // Test $cids array - ensure it does not contain cache id's that exist.
    $this->assertFalse(in_array('test2', $cids), "Existing cache id test2 is not in cids array.");
    $this->assertFalse(in_array('test7', $cids), "Existing cache id test7 is not in cids array.");
    $this->assertFalse(in_array('test19', $cids), "Added cache id test19 is not in cids array.");
  }

  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::isEmpty().
   */
  public function testIsEmpty() {
    $backend = $this->getCacheBackend();

    $this->assertTrue($backend->isEmpty(), "Backend is empty.");

    $backend->set('pony', "Shetland");
    $this->assertFalse($backend->isEmpty(), "Backend is not empty.");

    $backend->delete('pony');
    $this->assertTrue($backend->isEmpty(), "Backend is empty.");
  }

  /**
   * Test Drupal\Core\Cache\CacheBackendInterface::delete() and
   * Drupal\Core\Cache\CacheBackendInterface::deleteMultiple().
   */
  public function testDeleteMultiple() {
    $backend = $this->getCacheBackend();

    // Set numerous testing keys.
    $backend->set('test1', 1);
    $backend->set('test2', 3);
    $backend->set('test3', 5);
    $backend->set('test4', 7);
    $backend->set('test5', 11);
    $backend->set('test6', 13);
    $backend->set('test7', 17);

    $backend->delete('test1');
    $backend->delete('test23'); // Nonexistent key should not cause an error.
    $backend->deleteMultiple(array(
      'test3',
      'test5',
      'test7',
      'test19', // Nonexistent key should not cause an error.
      'test21', // Nonexistent key should not cause an error.
    ));

    // Test if expected keys have been deleted.
    $this->assertIdentical(FALSE, $backend->get('test1'), "Cache id test1 deleted.");
    $this->assertIdentical(FALSE, $backend->get('test3'), "Cache id test3 deleted.");
    $this->assertIdentical(FALSE, $backend->get('test5'), "Cache id test5 deleted.");
    $this->assertIdentical(FALSE, $backend->get('test7'), "Cache id test7 deleted.");

    // Test if expected keys exist.
    $this->assertNotIdentical(FALSE, $backend->get('test2'), "Cache id test2 exists.");
    $this->assertNotIdentical(FALSE, $backend->get('test4'), "Cache id test4 exists.");
    $this->assertNotIdentical(FALSE, $backend->get('test6'), "Cache id test6 exists.");

    // Test if that expected keys do not exist.
    $this->assertIdentical(FALSE, $backend->get('test19'), "Cache id test19 does not exist.");
    $this->assertIdentical(FALSE, $backend->get('test21'), "Cache id test21 does not exist.");
  }

  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::deleteTags().
   */
  function testDeleteTags() {
    $backend = $this->getCacheBackend();

    // Create two cache entries with the same tag and tag value.
    $backend->set('test_cid_invalidate1', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => 2));
    $backend->set('test_cid_invalidate2', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => 2));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'), 'Two cache items were created.');

    // Delete test_tag of value 1. This should delete both entries.
    $backend->deleteTags(array('test_tag' => 2));
    $this->assertFalse($backend->get('test_cid_invalidate1') || $backend->get('test_cid_invalidate2'), 'Two cache items invalidated after deleting a cache tag.');
    $this->assertFalse($backend->get('test_cid_invalidate1', TRUE) || $backend->get('test_cid_invalidate2', TRUE), 'Two cache items deleted after deleting a cache tag.');

    // Create two cache entries with the same tag and an array tag value.
    $backend->set('test_cid_invalidate1', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => array(1)));
    $backend->set('test_cid_invalidate2', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => array(1)));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'), 'Two cache items were created.');

    // Delete test_tag of value 1. This should delete both entries.
    $backend->deleteTags(array('test_tag' => array(1)));
    $this->assertFalse($backend->get('test_cid_invalidate1') || $backend->get('test_cid_invalidate2'), 'Two cache items invalidated after deleted a cache tag.');
    $this->assertFalse($backend->get('test_cid_invalidate1', TRUE) || $backend->get('test_cid_invalidate2', TRUE), 'Two cache items deleted after deleting a cache tag.');

    // Create three cache entries with a mix of tags and tag values.
    $backend->set('test_cid_invalidate1', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => array(1)));
    $backend->set('test_cid_invalidate2', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => array(2)));
    $backend->set('test_cid_invalidate3', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag_foo' => array(3)));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2') && $backend->get('test_cid_invalidate3'), 'Three cached items were created.');
    $backend->deleteTags(array('test_tag_foo' => array(3)));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'), 'Cached items not matching the tag were not deleted.');
    $this->assertFalse($backend->get('test_cid_invalidated3', TRUE), 'Cache item matching the tag was deleted.');

    // Create cache entry in multiple bins. Two cache entries
    // (test_cid_invalidate1 and test_cid_invalidate2) still exist from previous
    // tests.
    $tags = array('test_tag' => array(1, 2, 3));
    $bins = array('path', 'bootstrap', 'page');
    foreach ($bins as $bin) {
      $this->getCacheBackend($bin)->set('test', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, $tags);
      $this->assertTrue($this->getCacheBackend($bin)->get('test'), 'Cache item was set in bin.');
    }

    // Delete tag in mulitple bins.
    foreach ($bins as $bin) {
      $this->getCacheBackend($bin)->deleteTags(array('test_tag' => array(2)));
    }

    // Test that cache entry has been deleted in multple bins.
    foreach ($bins as $bin) {
      $this->assertFalse($this->getCacheBackend($bin)->get('test', TRUE), 'Tag deletion affected item in bin.');
    }
    // Test that the cache entry with a matching tag has been invalidated.
    $this->assertFalse($this->getCacheBackend($bin)->get('test_cid_invalidate2', TRUE), 'Cache items matching tag were deleted.');
    // Test that the cache entry with without a matching tag still exists.
    $this->assertTrue($this->getCacheBackend($bin)->get('test_cid_invalidate1'), 'Cache items not matching tag were not invalidated.');
  }

  /**
   * Test Drupal\Core\Cache\CacheBackendInterface::deleteAll().
   */
  public function testDeleteAll() {
    $backend = $this->getCacheBackend();

    // Set both expiring and permanent keys.
    $backend->set('test1', 1, CacheBackendInterface::CACHE_PERMANENT);
    $backend->set('test2', 3, time() + 1000);

    $backend->deleteAll();

    $this->assertTrue($backend->isEmpty(), "Backend is empty after deleteAll().");

    $this->assertFalse($backend->get('test1'), 'First key has been deleted.');
    $this->assertFalse($backend->get('test2'), 'Second key has been deleted.');
  }

  /**
   * Test Drupal\Core\Cache\CacheBackendInterface::invalidate() and
   * Drupal\Core\Cache\CacheBackendInterface::invalidateMultiple().
   */
  function testInvalidate() {
    $backend = $this->getCacheBackend();
    $backend->set('test1', 1);
    $backend->set('test2', 2);
    $backend->set('test3', 2);
    $backend->set('test4', 2);

    $reference = array('test1', 'test2', 'test3', 'test4');

    $cids = $reference;
    $ret = $backend->getMultiple($cids);
    $this->assertEqual(count($ret), 4, 'Four items returned.');

    $backend->invalidate('test1');
    $backend->invalidateMultiple(array('test2', 'test3'));

    $cids = $reference;
    $ret = $backend->getMultiple($cids);
    $this->assertEqual(count($ret), 1, 'Only one item element returned.');

    $cids = $reference;
    $ret = $backend->getMultiple($cids, TRUE);
    $this->assertEqual(count($ret), 4, 'Four items returned.');
  }

  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::invalidateTags().
   */
  function testInvalidateTags() {
    $backend = $this->getCacheBackend();

    // Create two cache entries with the same tag and tag value.
    $backend->set('test_cid_invalidate1', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => 2));
    $backend->set('test_cid_invalidate2', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => 2));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'), 'Two cache items were created.');

    // Invalidate test_tag of value 1. This should invalidate both entries.
    $backend->invalidateTags(array('test_tag' => 2));
    $this->assertFalse($backend->get('test_cid_invalidate1') || $backend->get('test_cid_invalidate2'), 'Two cache items invalidated after invalidating a cache tag.');
    $this->assertTrue($backend->get('test_cid_invalidate1', TRUE) && $backend->get('test_cid_invalidate2', TRUE), 'Cache items not deleted after invalidating a cache tag.');

    // Create two cache entries with the same tag and an array tag value.
    $backend->set('test_cid_invalidate1', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => array(1)));
    $backend->set('test_cid_invalidate2', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => array(1)));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'), 'Two cache items were created.');

    // Invalidate test_tag of value 1. This should invalidate both entries.
    $backend->invalidateTags(array('test_tag' => array(1)));
    $this->assertFalse($backend->get('test_cid_invalidate1') || $backend->get('test_cid_invalidate2'), 'Two caches removed after invalidating a cache tag.');
    $this->assertTrue($backend->get('test_cid_invalidate1', TRUE) && $backend->get('test_cid_invalidate2', TRUE), 'Cache items not deleted after invalidating a cache tag.');

    // Create three cache entries with a mix of tags and tag values.
    $backend->set('test_cid_invalidate1', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => array(1)));
    $backend->set('test_cid_invalidate2', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag' => array(2)));
    $backend->set('test_cid_invalidate3', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, array('test_tag_foo' => array(3)));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2') && $backend->get('test_cid_invalidate3'), 'Three cached items were created.');
    $backend->invalidateTags(array('test_tag_foo' => array(3)));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'), 'Cache items not matching the tag were not invalidated.');
    $this->assertFalse($backend->get('test_cid_invalidated3'), 'Cached item matching the tag was removed.');

    // Create cache entry in multiple bins. Two cache entries
    // (test_cid_invalidate1 and test_cid_invalidate2) still exist from previous
    // tests.
    $tags = array('test_tag' => array(1, 2, 3));
    $bins = array('path', 'bootstrap', 'page');
    foreach ($bins as $bin) {
      $this->getCacheBackend($bin)->set('test', $this->defaultValue, CacheBackendInterface::CACHE_PERMANENT, $tags);
      $this->assertTrue($this->getCacheBackend($bin)->get('test'), 'Cache item was set in bin.');
    }

    // Invalidate tag in mulitple bins.
    foreach ($bins as $bin) {
      $this->getCacheBackend($bin)->invalidateTags(array('test_tag' => array(2)));
    }

    // Test that cache entry has been invalidated in multple bins.
    foreach ($bins as $bin) {
      $this->assertFalse($this->getCacheBackend($bin)->get('test'), 'Tag invalidation affected item in bin.');
    }
    // Test that the cache entry with a matching tag has been invalidated.
    $this->assertFalse($this->getCacheBackend($bin)->get('test_cid_invalidate2'), 'Cache items matching tag were invalidated.');
    // Test that the cache entry with without a matching tag still exists.
    $this->assertTrue($this->getCacheBackend($bin)->get('test_cid_invalidate1'), 'Cache items not matching tag were not invalidated.');
  }

  /**
   * Test Drupal\Core\Cache\CacheBackendInterface::invalidateAll().
   */
  public function testInvalidateAll() {
    $backend = $this->getCacheBackend();

    // Set both expiring and permanent keys.
    $backend->set('test1', 1, CacheBackendInterface::CACHE_PERMANENT);
    $backend->set('test2', 3, time() + 1000);

    $backend->invalidateAll();

    $this->assertFalse($backend->get('test1'), 'First key has been invalidated.');
    $this->assertFalse($backend->get('test2'), 'Second key has been invalidated.');
    $this->assertTrue($backend->get('test1', TRUE), 'First key has not been deleted.');
    $this->assertTrue($backend->get('test2', TRUE), 'Second key has not been deleted.');
  }

}
