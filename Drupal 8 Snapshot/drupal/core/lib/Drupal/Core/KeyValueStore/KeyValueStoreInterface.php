<?php

/**
 * @file
 * Contains Drupal\Core\KeyValueStore\KeyValueStoreInterface.
 */

namespace Drupal\Core\KeyValueStore;

/**
 * Defines the interface for key/value store implementations.
 */
interface KeyValueStoreInterface {

  /**
   * Returns the name of this collection.
   *
   * @return string
   *   The name of this collection.
   */
  public function getCollectionName();

  /**
   * Returns the stored value for a given key.
   *
   * @param string $key
   *   The key of the data to retrieve.
   *
   * @return mixed
   *   The stored value, or NULL if no value exists.
   */
  public function get($key);

  /**
   * Returns the stored key/value pairs for a given set of keys.
   *
   * @param array $keys
   *   A list of keys to retrieve.
   *
   * @return array
   *   An associative array of items successfully returned, indexed by key.
   *
   * @todo What's returned for non-existing keys?
   */
  public function getMultiple(array $keys);

  /**
   * Returns all stored key/value pairs in the collection.
   *
   * @return array
   *   An associative array containing all stored items in the collection.
   */
  public function getAll();

  /**
   * Saves a value for a given key.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   */
  public function set($key, $value);

  /**
   * Saves a value for a given key if it does not exist yet.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   *
   * @return bool
   *   TRUE if the data was set, FALSE if it already existed.
   */
  public function setIfNotExists($key, $value);

  /**
   * Saves key/value pairs.
   *
   * @param array $data
   *   An associative array of key/value pairs.
   */
  public function setMultiple(array $data);

  /**
   * Deletes an item from the key/value store.
   *
   * @param string $key
   *   The item name to delete.
   */
  public function delete($key);

  /**
   * Deletes multiple items from the key/value store.
   *
   * @param array $keys
   *   A list of item names to delete.
   */
  public function deleteMultiple(array $keys);

  /**
   * Deletes all items from the key/value store.
   */
  public function deleteAll();

}
