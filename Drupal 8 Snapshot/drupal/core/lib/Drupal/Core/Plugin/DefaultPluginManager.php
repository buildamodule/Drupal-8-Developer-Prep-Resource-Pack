<?php

/**
 * @file
 * Contains \Drupal\Core\Plugin\DefaultPluginManager
 */

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Component\Plugin\PluginManagerBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Language\Language;
use Drupal\Core\Plugin\Discovery\AnnotatedClassDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;

/**
 * Base class for plugin managers.
 */
class DefaultPluginManager extends PluginManagerBase implements PluginManagerInterface, CachedDiscoveryInterface {

  /**
   * Cached definitions array.
   *
   * @var array
   */
  protected $definitions;

  /**
   * Cache backend instance.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Provided cache key prefix.
   *
   * @var string
   */
  protected $cacheKeyPrefix;

  /**
   * Actually used cache key with the language code appended.
   *
   * @var string
   */
  protected $cacheKey;

  /**
   * Name of the alter hook if one should be invoked.
   *
   * @var string
   */
  protected $alterHook;

  /**
   * The subdirectory within a namespace to look for plugins, or FALSE if the
   * plugins are in the top level of the namespace.
   *
   * @var string|bool
   */
  protected $subdir;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Creates the discovery object.
   *
   * @param string|bool $subdir
   *   The plugin's subdirectory, for example Plugin/views/filter.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param array $annotation_namespaces
   *   (optional) The namespaces of classes that can be used as annotations.
   *   Defaults to an empty array.
   * @param string $plugin_definition_annotation_name
   *   (optional) The name of the annotation that contains the plugin definition.
   *   Defaults to 'Drupal\Component\Annotation\Plugin'.
   */
  public function __construct($subdir, \Traversable $namespaces, $annotation_namespaces = array(), $plugin_definition_annotation_name = 'Drupal\Component\Annotation\Plugin') {
    $this->subdir = $subdir;
    $this->discovery = new AnnotatedClassDiscovery($subdir, $namespaces, $annotation_namespaces, $plugin_definition_annotation_name);
    $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    $this->factory = new ContainerFactory($this);
  }

  /**
   * Initialize the cache backend.
   *
   * Plugin definitions are cached using the provided cache backend. The
   * interface language is added as a suffix to the cache key.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Language\LanguageManager
   *   The language manager.
   * @param string $cache_key_prefix
   *   Cache key prefix to use, the language code will be appended
   *   automatically.
   */
  public function setCacheBackend(CacheBackendInterface $cache_backend, LanguageManager $language_manager, $cache_key_prefix) {
    $this->languageManager = $language_manager;
    $this->cacheBackend = $cache_backend;
    $this->cacheKeyPrefix = $cache_key_prefix;
    $this->cacheKey = $cache_key_prefix . ':' . $language_manager->getLanguage(Language::TYPE_INTERFACE)->id;
  }

  /**
   * Initializes the alter hook.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param string $alter_hook
   *   Name of the alter hook.
   */
  protected function alterInfo(ModuleHandlerInterface $module_handler, $alter_hook) {
    $this->moduleHandler = $module_handler;
    $this->alterHook = $alter_hook;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id) {
    // Fetch definitions if they're not loaded yet.
    if (!isset($this->definitions)) {
      $this->getDefinitions();
    }
    // Avoid using a ternary that would create a copy of the array.
    if (isset($this->definitions[$plugin_id])) {
      return $this->definitions[$plugin_id];
    }
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = $this->getCachedDefinitions();
    if (!isset($definitions)) {
      $definitions = $this->findDefinitions();
      $this->setCachedDefinitions($definitions);
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    if ($this->cacheBackend) {
      $cache_keys = array();
      // @todo: Use $this->languageManager->languageList() after http://drupal.org/node/1862202 is in.
      foreach (language_list() as $langcode => $language) {
        $cache_keys[] = $this->cacheKeyPrefix . ':' .$langcode;
      }
      $this->cacheBackend->deleteMultiple($cache_keys);
    }
    $this->definitions = NULL;
  }

  /**
   * Returns the cached plugin definitions of the decorated discovery class.
   *
   * @return array|NULL
   *   On success this will return an array of plugin definitions. On failure
   *   this should return NULL, indicating to other methods that this has not
   *   yet been defined. Success with no values should return as an empty array
   *   and would actually be returned by the getDefinitions() method.
   */
  protected function getCachedDefinitions() {
    if (!isset($this->definitions) && $this->cacheBackend && $cache = $this->cacheBackend->get($this->cacheKey)) {
      $this->definitions = $cache->data;
    }
    return $this->definitions;
  }

  /**
   * Sets a cache of plugin definitions for the decorated discovery class.
   *
   * @param array $definitions
   *   List of definitions to store in cache.
   */
  protected function setCachedDefinitions($definitions) {
    if ($this->cacheBackend) {
      $this->cacheBackend->set($this->cacheKey, $definitions);
    }
    $this->definitions = $definitions;
  }

  /**
   * Finds plugin definitions.
   *
   * @return array
   *   List of definitions to store in cache.
   */
  protected function findDefinitions() {
    $definitions = $this->discovery->getDefinitions();
    foreach ($definitions as $plugin_id => &$definition) {
      $this->processDefinition($definition, $plugin_id);
    }
    if ($this->alterHook) {
      $this->moduleHandler->alter($this->alterHook, $definitions);
    }
    return $definitions;
  }

}
