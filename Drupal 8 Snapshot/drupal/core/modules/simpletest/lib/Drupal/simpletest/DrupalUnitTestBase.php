<?php

/**
 * @file
 * Contains \Drupal\simpletest\DrupalUnitTestBase.
 */

namespace Drupal\simpletest;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DrupalKernel;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Symfony\Component\DependencyInjection\Reference;
use Drupal\Core\Database\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base test case class for Drupal unit tests.
 *
 * Tests extending this base class can access files and the database, but the
 * entire environment is initially empty. Drupal runs in a minimal mocked
 * environment, comparable to the one in the installer or update.php.
 *
 * The module/hook system is functional and operates on a fixed module list.
 * Additional modules needed in a test may be loaded and added to the fixed
 * module list.
 *
 * @see DrupalUnitTestBase::$modules
 * @see DrupalUnitTestBase::enableModules()
 */
abstract class DrupalUnitTestBase extends UnitTestBase {

  /**
   * Modules to enable.
   *
   * Test classes extending this class, and any classes in the hierarchy up to
   * this class, may specify individual lists of modules to enable by setting
   * this property. The values of all properties in all classes in the hierarchy
   * are merged.
   *
   * Unlike UnitTestBase::setUp(), any modules specified in the $modules
   * property are automatically loaded and set as the fixed module list.
   *
   * Unlike WebTestBase::setUp(), the specified modules are loaded only, but not
   * automatically installed. Modules need to be installed manually, if needed.
   *
   * @see DrupalUnitTestBase::enableModules()
   * @see DrupalUnitTestBase::setUp()
   *
   * @var array
   */
  public static $modules = array();

  private $moduleFiles;
  private $themeFiles;
  private $themeData;

  /**
   * A KeyValueMemoryFactory instance to use when building the container.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueMemoryFactory.
   */
  protected $keyValueFactory;

  /**
   * Overrides \Drupal\simpletest\UnitTestBase::__construct().
   */
  function __construct($test_id = NULL) {
    parent::__construct($test_id);
    $this->skipClasses[__CLASS__] = TRUE;
  }

  /**
   * Sets up Drupal unit test environment.
   *
   * @see DrupalUnitTestBase::$modules
   * @see DrupalUnitTestBase
   */
  protected function setUp() {
    // Copy/prime extension file lists once to avoid filesystem scans.
    if (!isset($this->moduleFiles)) {
      $this->moduleFiles = \Drupal::state()->get('system.module.files') ?: array();
      $this->themeFiles = \Drupal::state()->get('system.theme.files') ?: array();
      $this->themeData = \Drupal::state()->get('system.theme.data') ?: array();
    }

    $this->keyValueFactory = new KeyValueMemoryFactory();

    parent::setUp();
    // Build a minimal, partially mocked environment for unit tests.
    $this->containerBuild(drupal_container());
    // Make sure it survives kernel rebuilds.
    $GLOBALS['conf']['container_service_providers']['TestServiceProvider'] = 'Drupal\simpletest\TestServiceProvider';

    \Drupal::state()->set('system.module.files', $this->moduleFiles);
    \Drupal::state()->set('system.theme.files', $this->themeFiles);
    \Drupal::state()->set('system.theme.data', $this->themeData);

    // Bootstrap the kernel.
    // No need to dump it; this test runs in-memory.
    $this->kernel = new DrupalKernel('unit_testing', drupal_classloader(), FALSE);
    $this->kernel->boot();

    // Collect and set a fixed module list.
    $class = get_class($this);
    $modules = array();
    while ($class) {
      if (property_exists($class, 'modules')) {
        // Only add the modules, if the $modules property was not inherited.
        $rp = new \ReflectionProperty($class, 'modules');
        if ($rp->class == $class) {
          $modules[$class] = $class::$modules;
        }
      }
      $class = get_parent_class($class);
    }
    // Modules have been collected in reverse class hierarchy order; modules
    // defined by base classes should be sorted first. Then, merge the results
    // together.
    $modules = array_reverse($modules);
    $modules = call_user_func_array('array_merge_recursive', $modules);
    $this->enableModules($modules, FALSE);
    // In order to use theme functions default theme config needs to exist.
    \Drupal::config('system.theme')->set('default', 'stark');
  }

  protected function tearDown() {
    $this->kernel->shutdown();
    parent::tearDown();
  }

  /**
   * Sets up the base service container for this test.
   *
   * Extend this method in your test to register additional service overrides
   * that need to persist a DrupalKernel reboot. This method is called whenever
   * the kernel is rebuilt.
   *
   * @see DrupalUnitTestBase::setUp()
   * @see DrupalUnitTestBase::enableModules()
   * @see DrupalUnitTestBase::disableModules()
   */
  public function containerBuild(ContainerBuilder $container) {
    global $conf;
    // Keep the container object around for tests.
    $this->container = $container;

    $container->register('lock', 'Drupal\Core\Lock\NullLockBackend');
    $this->settingsSet('cache', array('default' => 'cache.backend.memory'));

    $container
      ->register('config.storage', 'Drupal\Core\Config\FileStorage')
      ->addArgument($this->configDirectories[CONFIG_ACTIVE_DIRECTORY]);

    $conf['keyvalue_default'] = 'keyvalue.memory';
    $container->set('keyvalue.memory', $this->keyValueFactory);
    if (!$container->has('keyvalue')) {
      // TestBase::setUp puts a completely empty container in
      // drupal_container() which is somewhat the mirror of the empty
      // environment being set up. Unit tests need not to waste time with
      // getting a container set up for them. Drupal Unit Tests might just get
      // away with a simple container holding the absolute bare minimum. When
      // a kernel is overridden then there's no need to re-register the keyvalue
      // service but when a test is happy with the superminimal container put
      // together here, it still might a keyvalue storage for anything (for
      // eg. module_enable) using \Drupal::state() -- that's why a memory
      // service was added in the first place.
      $container
        ->register('keyvalue', 'Drupal\Core\KeyValueStore\KeyValueFactory')
        ->addArgument(new Reference('service_container'));

      $container->register('state', 'Drupal\Core\KeyValueStore\KeyValueStoreInterface')
        ->setFactoryService(new Reference('keyvalue'))
        ->setFactoryMethod('get')
        ->addArgument('state');
    }

    if ($container->hasDefinition('path_processor_alias')) {
      // Prevent the alias-based path processor, which requires a url_alias db
      // table, from being registered to the path processor manager. We do this
      // by removing the tags that the compiler pass looks for. This means the
      // url generator can safely be used within DUTB tests.
      $definition = $container->getDefinition('path_processor_alias');
      $definition->clearTag('path_processor_inbound')->clearTag('path_processor_outbound');
    }

  }

  /**
   * Installs default configuration for a given list of modules.
   *
   * @param array $modules
   *   A list of modules for which to install default configuration.
   */
  protected function installConfig(array $modules) {
    foreach ($modules as $module) {
      if (!$this->container->get('module_handler')->moduleExists($module)) {
        throw new \RuntimeException(format_string("'@module' module is not enabled.", array(
          '@module' => $module,
        )));
      }
      config_install_default_config('module', $module);
    }
    $this->pass(format_string('Installed default config: %modules.', array(
      '%modules' => implode(', ', $modules),
    )));
  }

  /**
   * Installs a specific table from a module schema definition.
   *
   * @param string $module
   *   The name of the module that defines the table's schema.
   * @param string|array $tables
   *   The name or an array of the names of the tables to install.
   */
  protected function installSchema($module, $tables) {
    // drupal_get_schema_unprocessed() is technically able to install a schema
    // of a non-enabled module, but its ability to load the module's .install
    // file depends on many other factors. To prevent differences in test
    // behavior and non-reproducible test failures, we only allow the schema of
    // explicitly loaded/enabled modules to be installed.
    if (!$this->container->get('module_handler')->moduleExists($module)) {
      throw new \RuntimeException(format_string("'@module' module is not enabled.", array(
        '@module' => $module,
      )));
    }
    $tables = (array) $tables;
    foreach ($tables as $table) {
      $schema = drupal_get_schema_unprocessed($module, $table);
      if (empty($schema)) {
        throw new \RuntimeException(format_string("Unknown '@table' table schema in '@module' module.", array(
          '@module' => $module,
          '@table' => $table,
        )));
      }
      $this->container->get('database')->schema()->createTable($table, $schema);
    }
    // We need to refresh the schema cache, as any call to drupal_get_schema()
    // would not know of/return the schema otherwise.
    // @todo Refactor Schema API to make this obsolete.
    drupal_get_schema(NULL, TRUE);
    $this->pass(format_string('Installed %module tables: %tables.', array(
      '%tables' => '{' . implode('}, {', $tables) . '}',
      '%module' => $module,
    )));
  }

  /**
   * Enables modules for this test.
   *
   * @param array $modules
   *   A list of modules to enable. Dependencies are not resolved; i.e.,
   *   multiple modules have to be specified with dependent modules first.
   *   The new modules are only added to the active module list and loaded.
   */
  protected function enableModules(array $modules) {
    // Set the list of modules in the extension handler.
    $module_handler = $this->container->get('module_handler');
    $module_filenames = $module_handler->getModuleList();
    foreach ($modules as $module) {
      $module_filenames[$module] = drupal_get_filename('module', $module);
    }
    $module_handler->setModuleList($module_filenames);
    $module_handler->resetImplementations();
    // Update the kernel to make their services available.
    $this->kernel->updateModules($module_filenames, $module_filenames);

    // Ensure isLoaded() is TRUE in order to make theme() work.
    // Note that the kernel has rebuilt the container; this $module_handler is
    // no longer the $module_handler instance from above.
    $module_handler = $this->container->get('module_handler');
    $module_handler->reload();
    $this->pass(format_string('Enabled modules: %modules.', array(
      '%modules' => implode(', ', $modules),
    )));
  }

  /**
   * Disables modules for this test.
   *
   * @param array $modules
   *   A list of modules to disable. Dependencies are not resolved; i.e.,
   *   multiple modules have to be specified with dependent modules first.
   *   Code of previously active modules is still loaded. The modules are only
   *   removed from the active module list.
   */
  protected function disableModules(array $modules) {
    // Unset the list of modules in the extension handler.
    $module_handler = $this->container->get('module_handler');
    $module_filenames = $module_handler->getModuleList();
    foreach ($modules as $module) {
      unset($module_filenames[$module]);
    }
    $module_handler->setModuleList($module_filenames);
    $module_handler->resetImplementations();
    // Update the kernel to remove their services.
    $this->kernel->updateModules($module_filenames, $module_filenames);

    // Ensure isLoaded() is TRUE in order to make theme() work.
    // Note that the kernel has rebuilt the container; this $module_handler is
    // no longer the $module_handler instance from above.
    $module_handler = $this->container->get('module_handler');
    $module_handler->reload();
    $this->pass(format_string('Disabled modules: %modules.', array(
      '%modules' => implode(', ', $modules),
    )));
  }

}
