<?php

/**
 * @file
 * Contains Drupal.
 */

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Static Service Container wrapper.
 *
 * Generally, code in Drupal should accept its dependencies via either
 * constructor injection or setter method injection. However, there are cases,
 * particularly in legacy procedural code, where that is infeasible. This
 * class acts as a unified global accessor to arbitrary services within the
 * system in order to ease the transition from procedural code to injected OO
 * code.
 *
 * The container is built by the kernel and passed in to this class which stores
 * it statically. The container always contains the services from
 * \Drupal\Core\CoreServiceProvider, the service providers of enabled modules and any other
 * service providers defined in $GLOBALS['conf']['container_service_providers'].
 *
 * This class exists only to support legacy code that cannot be dependency
 * injected. If your code needs it, consider refactoring it to be object
 * oriented, if possible. When this is not possible, for instance in the case of
 * hook implementations, and your code is more than a few non-reusable lines, it
 * is recommended to instantiate an object implementing the actual logic.
 *
 * @code
 *   // Legacy procedural code.
 *   function hook_do_stuff() {
 *     $lock = lock()->acquire('stuff_lock');
 *     // ...
 *   }
 *
 *   // Correct procedural code.
 *   function hook_do_stuff() {
 *     $lock = Drupal::lock()->acquire('stuff_lock');
 *     // ...
 *   }
 *
 *   // The preferred way: dependency injected code.
 *   function hook_do_stuff() {
 *     // Move the actual implementation to a class and instantiate it.
 *     $instance = new StuffDoingClass(Drupal::lock());
 *     $instance->doStuff();
 *
 *     // Or, even better, rely on the service container to avoid hard coding a
 *     // specific interface implementation, so that the actual logic can be
 *     // swapped. This might not always make sense, but in general it is a good
 *     // practice.
 *     Drupal::service('stuff.doing')->doStuff();
 *   }
 *
 *   interface StuffDoingInterface {
 *     public function doStuff();
 *   }
 *
 *   class StuffDoingClass implements StuffDoingInterface {
 *     protected $lockBackend;
 *
 *     public function __construct(LockBackendInterface $lockBackend) {
 *       $this->lockBackend = $lockBackend;
 *     }
 *
 *     public function doStuff() {
 *       $lock = $this->lockBackend->acquire('stuff_lock');
 *       // ...
 *     }
 *   }
 * @endcode
 *
 * @see \Drupal\Core\DrupalKernel
 */
class Drupal {

  /**
   * The currently active container object.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected static $container;

  /**
   * Sets a new global container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   A new container instance to replace the current.
   */
  public static function setContainer(ContainerInterface $container) {
    static::$container = $container;
  }

  /**
   * Returns the currently active global container.
   *
   * @deprecated This method is only useful for the testing environment, and as
   *   a BC shiv for drupal_container(). It should not be used otherwise.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   */
  public static function getContainer() {
    return static::$container;
  }

  /**
   * Retrieves a service from the container.
   *
   * Use this method if the desired service is not one of those with a dedicated
   * accessor method below. If it is listed below, those methods are preferred
   * as they can return useful type hints.
   *
   * @param string $id
   *   The ID of the service to retrieve.
   * @return mixed
   *   The specified service.
   */
  public static function service($id) {
    return static::$container->get($id);
  }

  /**
   * Retrieves the currently active request object.
   *
   * Note: The use of this wrapper in particular is especially discouraged. Most
   * code should not need to access the request directly.  Doing so means it
   * will only function when handling an HTTP request, and will require special
   * modification or wrapping when run from a command line tool, from certain
   * queue processors, or from automated tests.
   *
   * If code must access the request, it is considerably better to register
   * an object with the Service Container and give it a setRequest() method
   * that is configured to run when the service is created.  That way, the
   * correct request object can always be provided by the container and the
   * service can still be unit tested.
   *
   * If this method must be used, never save the request object that is
   * returned.  Doing so may lead to inconsistencies as the request object is
   * volatile and may change at various times, such as during a subrequest.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The currently active request object.
   */
  public static function request() {
    return static::$container->get('request');
  }

  /**
   * Retrieves the entity manager service.
   *
   * @return \Drupal\Core\Entity\EntityManager
   *   The entity manager service.
   */
  public static function entityManager() {
    return static::$container->get('plugin.manager.entity');
  }

  /**
   * Returns the current primary database.
   *
   * @return \Drupal\Core\Database\Connection
   *   The current active database's master connection.
   */
  public static function database() {
    return static::$container->get('database');
  }

  /**
   * Returns the requested cache bin.
   *
   * @param string $bin
   *   (optional) The cache bin for which the cache object should be returned,
   *   defaults to 'cache'.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The cache object associated with the specified bin.
   */
  public static function cache($bin = 'cache') {
    return static::$container->get('cache.' . $bin);
  }

  /**
   * Returns an expirable key value store collection.
   *
   * @param string $collection
   *   The name of the collection holding key and value pairs.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   *   An expirable key value store collection.
   */
  public static function keyValueExpirable($collection) {
    return static::$container->get('keyvalue.expirable')->get($collection);
  }

  /**
   * Returns the locking layer instance.
   *
   * @return \Drupal\Core\Lock\LockBackendInterface
   */
  public static function lock() {
    return static::$container->get('lock');
  }

  /**
   * Retrieves a configuration object.
   *
   * This is the main entry point to the configuration API. Calling
   * @code \Drupal::config('book.admin') @endcode will return a configuration
   * object in which the book module can store its administrative settings.
   *
   * @param string $name
   *   The name of the configuration object to retrieve. The name corresponds to
   *   a configuration file. For @code \Drupal::config('book.admin') @endcode, the config
   *   object returned will contain the contents of book.admin configuration file.
   *
   * @return \Drupal\Core\Config\Config
   *   A configuration object.
   */
  public static function config($name) {
    return static::$container->get('config.factory')->get($name);
  }

  /**
   * Returns a queue for the given queue name.
   *
   * The following variables can be set by variable_set or $conf overrides:
   * - queue_class_$name: The class to be used for the queue $name.
   * - queue_default_class: The class to use when queue_class_$name is not
   *   defined. Defaults to \Drupal\Core\Queue\System, a reliable backend using
   *   SQL.
   * - queue_default_reliable_class: The class to use when queue_class_$name is
   *   not defined and the queue_default_class is not reliable. Defaults to
   *   \Drupal\Core\Queue\System.
   *
   * @param string $name
   *   The name of the queue to work with.
   * @param bool $reliable
   *   (optional) TRUE if the ordering of items and guaranteeing every item
   *   executes at least once is important, FALSE if scalability is the main
   *   concern. Defaults to FALSE.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue object for a given name.
   */
  public static function queue($name, $reliable = FALSE) {
    return static::$container->get('queue')->get($name, $reliable);
  }

  /**
   * Returns a key/value storage collection.
   *
   * @param string $collection
   *   Name of the key/value collection to return.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  public static function keyValue($collection) {
    return static::$container->get('keyvalue')->get($collection);
  }

  /**
   * Returns the state storage service.
   *
   * Use this to store machine-generated data, local to a specific environment
   * that does not need deploying and does not need human editing; for example,
   * the last time cron was run. Data which needs to be edited by humans and
   * needs to be the same across development, production, etc. environments
   * (for example, the system maintenance message) should use \Drupal::config() instead.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  public static function state() {
    return static::$container->get('state');
  }

  /**
   * Returns the default http client.
   *
   * @return \Guzzle\Http\ClientInterface
   *   A guzzle http client instance.
   */
  public static function httpClient() {
    return static::$container->get('http_default_client');
  }

  /**
   * Returns the entity query object for this entity type.
   *
   * @param string $entity_type
   *   The entity type, e.g. node, for which the query object should be
   *   returned.
   * @param string $conjunction
   *   AND if all conditions in the query need to apply, OR if any of them is
   *   enough. Optional, defaults to AND.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query object that can query the given entity type.
   */
  public static function entityQuery($entity_type, $conjunction = 'AND') {
    return static::$container->get('entity.query')->get($entity_type, $conjunction);
  }

  /**
   * Returns the entity query aggregate object for this entity type.
   *
   * @param string $entity_type
   *   The entity type, e.g. node, for which the query object should be
   *   returned.
   * @param string $conjunction
   *   AND if all conditions in the query need to apply, OR if any of them is
   *   enough. Optional, defaults to AND.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query object that can query the given entity type.
   */
  public static function entityQueryAggregate($entity_type, $conjunction = 'AND') {
    return static::$container->get('entity.query')->getAggregate($entity_type, $conjunction);
  }

  /**
   * Returns the flood instance.
   *
   * @return \Drupal\Core\Flood\FloodInterface
   */
  public static function flood() {
    return static::$container->get('flood');
  }

  /**
   * Returns the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   */
  public static function moduleHandler() {
    return static::$container->get('module_handler');
  }

  /**
   * Returns the typed data manager service.
   *
   * Use the typed data manager service for creating typed data objects.
   *
   * @return \Drupal\Core\TypedData\TypedDataManager
   *   The typed data manager.
   *
   * @see \Drupal\Core\TypedData\TypedDataManager::create()
   */
  public static function typedData() {
    return static::$container->get('typed_data');
  }

  /**
   * Returns the token service.
   *
   * @return \Drupal\Core\Utility\Token
   *   The token service.
   */
  public static function token() {
    return static::$container->get('token');
  }

  /**
   * Returns the url generator service.
   *
   * @return \Drupal\Core\Routing\PathBasedGeneratorInterface
   *   The url generator service.
   */
  public static function urlGenerator() {
    return static::$container->get('url_generator');
  }

  /**
   * Returns the string translation service.
   *
   * @return \Drupal\Core\StringTranslation\TranslationManager
   *   The string translation manager.
   */
  public static function translation() {
    return static::$container->get('string_translation');
  }

  /**
   * Returns the language manager service.
   *
   * @return \Drupal\Core\Language\LanguageManager
   *   The language manager.
   */
  public static function languageManager() {
    return static::$container->get('language_manager');
  }

  /**
   * Returns the CSRF token manager service.
   *
   * @return \Drupal\Core\Access\CsrfTokenGenerator
   *   The CSRF token manager.
   */
  public static function csrfToken() {
    return static::$container->get('csrf_token');
  }

}
