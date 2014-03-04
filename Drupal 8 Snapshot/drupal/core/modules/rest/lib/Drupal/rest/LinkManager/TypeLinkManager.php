<?php

/**
 * @file
 * Contains \Drupal\rest\LinkManager\TypeLinkManager.
 */

namespace Drupal\rest\LinkManager;

use Drupal\Core\Cache\CacheBackendInterface;

class TypeLinkManager implements TypeLinkManagerInterface {

  /**
   * Injected cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface;
   */
  protected $cache;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The injected cache backend for caching type URIs.
   */
  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }

  /**
   * Get a type link for a bundle.
   *
   * @param string $entity_type
   *   The bundle's entity type.
   * @param string $bundle
   *   The name of the bundle.
   *
   * @return array
   *   The URI that identifies this bundle.
   */
  public function getTypeUri($entity_type, $bundle) {
    // @todo Make the base path configurable.
    return url("rest/type/$entity_type/$bundle", array('absolute' => TRUE));
  }

  /**
   * Implements \Drupal\rest\LinkManager\TypeLinkManagerInterface::getTypeInternalIds().
   */
  public function getTypeInternalIds($type_uri) {
    $types = $this->getTypes();
    if (isset($types[$type_uri])) {
      return $types[$type_uri];
    }
    return FALSE;
  }

  /**
   * Get the array of type links.
   *
   * @return array
   *   An array of typed data ids (entity_type and bundle) keyed by
   *   corresponding type URI.
   */
  public function getTypes() {
    $cid = 'rest:links:types';
    $cache = $this->cache->get($cid);
    if (!$cache) {
      $this->writeCache();
      $cache = $this->cache->get($cid);
    }
    return $cache->data;
  }

  /**
   * Writes the cache of type links.
   */
  protected function writeCache() {
    $data = array();

    // Type URIs correspond to bundles. Iterate through the bundles to get the
    // URI and data for them.
    $entity_info = entity_get_info();
    foreach (entity_get_bundles() as $entity_type => $bundles) {
      $entity_type_info = $entity_info[$entity_type];
      $reflection = new \ReflectionClass($entity_type_info['class']);
      // Only content entities are supported currently.
      // @todo Consider supporting config entities.
      if ($reflection->implementsInterface('\Drupal\Core\Config\Entity\ConfigEntityInterface')) {
        continue;
      }
      foreach ($bundles as $bundle => $bundle_info) {
        // Get a type URI for the bundle.
        $bundle_uri = $this->getTypeUri($entity_type, $bundle);
        $data[$bundle_uri] = array(
          'entity_type' => $entity_type,
          'bundle' => $bundle,
        );
      }
    }
    // These URIs only change when entity info changes, so cache it permanently
    // and only clear it when entity_info is cleared.
    $this->cache->set('rest:links:types', $data, CacheBackendInterface::CACHE_PERMANENT, array('entity_info' => TRUE));
  }
}
