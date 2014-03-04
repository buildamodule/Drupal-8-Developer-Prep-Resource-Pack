<?php

/**
 * @file
 * Definition of \Drupal\simpletest\TestBase.
 */

namespace Drupal\simpletest;

use Drupal\Component\Utility\Random;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Settings;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Database\ConnectionNotDefinedException;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Language\Language;
use ReflectionMethod;
use ReflectionObject;

/**
 * Base class for Drupal tests.
 *
 * Do not extend this class directly, use either
 * \Drupal\simpletest\WebTestBaseBase or \Drupal\simpletest\UnitTestBaseBase.
 */
abstract class TestBase {
  /**
   * The test run ID.
   *
   * @var string
   */
  protected $testId;

  /**
   * The database prefix of this test run.
   *
   * @var string
   */
  protected $databasePrefix = NULL;

  /**
   * The original file directory, before it was changed for testing purposes.
   *
   * @var string
   */
  protected $originalFileDirectory = NULL;

  /**
   * Time limit for the test.
   */
  protected $timeLimit = 500;

  /**
   * Current results of this test case.
   *
   * @var Array
   */
  public $results = array(
    '#pass' => 0,
    '#fail' => 0,
    '#exception' => 0,
    '#debug' => 0,
  );

  /**
   * Assertions thrown in that test case.
   *
   * @var Array
   */
  protected $assertions = array();

  /**
   * This class is skipped when looking for the source of an assertion.
   *
   * When displaying which function an assert comes from, it's not too useful
   * to see "WebTestBase->drupalLogin()', we would like to see the test
   * that called it. So we need to skip the classes defining these helper
   * methods.
   */
  protected $skipClasses = array(__CLASS__ => TRUE);

  /**
   * Flag to indicate whether the test has been set up.
   *
   * The setUp() method isolates the test from the parent Drupal site by
   * creating a random prefix for the database and setting up a clean file
   * storage directory. The tearDown() method then cleans up this test
   * environment. We must ensure that setUp() has been run. Otherwise,
   * tearDown() will act on the parent Drupal site rather than the test
   * environment, destroying live data.
   */
  protected $setup = FALSE;

  protected $setupDatabasePrefix = FALSE;

  protected $setupEnvironment = FALSE;

  /**
   * TRUE if verbose debugging is enabled.
   *
   * @var boolean
   */
  protected $verbose = FALSE;

  /**
   * Incrementing identifier for verbose output filenames.
   *
   * @var integer
   */
  protected $verboseId = 0;

  /**
   * Safe class name for use in verbose output filenames.
   *
   * Namespaces separator (\) replaced with _.
   *
   * @var string
   */
  protected $verboseClassName;

  /**
   * Directory where verbose output files are put.
   *
   * @var string
   */
  protected $verboseDirectory;

  /**
   * The original database prefix when running inside Simpletest.
   *
   * @var string
   */
  protected $originalPrefix;

  /**
   * URL to the verbose output file directory.
   *
   * @var string
   */
  protected $verboseDirectoryUrl;

  /**
   * The settings array.
   */
  protected $originalSettings;

  /**
   * The public file directory for the test environment.
   *
   * This is set in TestBase::prepareEnvironment().
   *
   * @var string
   */
  protected $public_files_directory;

  /**
   * Whether to die in case any test assertion fails.
   *
   * @var boolean
   *
   * @see run-tests.sh
   */
  public $dieOnFail = FALSE;

  /**
   * The dependency injection container used in the test.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The config importer that can used in a test.
   *
   * @var \Drupal\Core\Config\ConfigImporter
   */
  protected $configImporter;

  /**
   * Constructor for Test.
   *
   * @param $test_id
   *   Tests with the same id are reported together.
   */
  public function __construct($test_id = NULL) {
    $this->testId = $test_id;
  }

  /**
   * Checks the matching requirements for Test.
   *
   * @return
   *   Array of errors containing a list of unmet requirements.
   */
  protected function checkRequirements() {
    return array();
  }

  /**
   * Internal helper: stores the assert.
   *
   * @param $status
   *   Can be 'pass', 'fail', 'exception', 'debug'.
   *   TRUE is a synonym for 'pass', FALSE for 'fail'.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   * @param $caller
   *   By default, the assert comes from a function whose name starts with
   *   'test'. Instead, you can specify where this assert originates from
   *   by passing in an associative array as $caller. Key 'file' is
   *   the name of the source file, 'line' is the line number and 'function'
   *   is the caller function itself.
   */
  protected function assert($status, $message = '', $group = 'Other', array $caller = NULL) {
    // Convert boolean status to string status.
    if (is_bool($status)) {
      $status = $status ? 'pass' : 'fail';
    }

    // Increment summary result counter.
    $this->results['#' . $status]++;

    // Get the function information about the call to the assertion method.
    if (!$caller) {
      $caller = $this->getAssertionCall();
    }

    // Creation assertion array that can be displayed while tests are running.
    $this->assertions[] = $assertion = array(
      'test_id' => $this->testId,
      'test_class' => get_class($this),
      'status' => $status,
      'message' => $message,
      'message_group' => $group,
      'function' => $caller['function'],
      'line' => $caller['line'],
      'file' => $caller['file'],
    );

    // Store assertion for display after the test has completed.
    self::getDatabaseConnection()
      ->insert('simpletest')
      ->fields($assertion)
      ->execute();

    // We do not use a ternary operator here to allow a breakpoint on
    // test failure.
    if ($status == 'pass') {
      return TRUE;
    }
    else {
      if ($this->dieOnFail && ($status == 'fail' || $status == 'exception')) {
        exit(1);
      }
      return FALSE;
    }
  }

  /**
   * Store an assertion from outside the testing context.
   *
   * This is useful for inserting assertions that can only be recorded after
   * the test case has been destroyed, such as PHP fatal errors. The caller
   * information is not automatically gathered since the caller is most likely
   * inserting the assertion on behalf of other code. In all other respects
   * the method behaves just like \Drupal\simpletest\TestBase::assert() in terms
   * of storing the assertion.
   *
   * @return
   *   Message ID of the stored assertion.
   *
   * @see \Drupal\simpletest\TestBase::assert()
   * @see \Drupal\simpletest\TestBase::deleteAssert()
   */
  public static function insertAssert($test_id, $test_class, $status, $message = '', $group = 'Other', array $caller = array()) {
    // Convert boolean status to string status.
    if (is_bool($status)) {
      $status = $status ? 'pass' : 'fail';
    }

    $caller += array(
      'function' => t('Unknown'),
      'line' => 0,
      'file' => t('Unknown'),
    );

    $assertion = array(
      'test_id' => $test_id,
      'test_class' => $test_class,
      'status' => $status,
      'message' => $message,
      'message_group' => $group,
      'function' => $caller['function'],
      'line' => $caller['line'],
      'file' => $caller['file'],
    );

    return self::getDatabaseConnection()
      ->insert('simpletest')
      ->fields($assertion)
      ->execute();
  }

  /**
   * Delete an assertion record by message ID.
   *
   * @param $message_id
   *   Message ID of the assertion to delete.
   *
   * @return
   *   TRUE if the assertion was deleted, FALSE otherwise.
   *
   * @see \Drupal\simpletest\TestBase::insertAssert()
   */
  public static function deleteAssert($message_id) {
    return (bool) self::getDatabaseConnection()
      ->delete('simpletest')
      ->condition('message_id', $message_id)
      ->execute();
  }

  /**
   * Returns the database connection to the site running Simpletest.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection to use for inserting assertions.
   */
  public static function getDatabaseConnection() {
    try {
      $connection = Database::getConnection('default', 'simpletest_original_default');
    }
    catch (ConnectionNotDefinedException $e) {
      // If the test was not set up, the simpletest_original_default
      // connection does not exist.
      $connection = Database::getConnection('default', 'default');
    }
    return $connection;
  }

  /**
   * Cycles through backtrace until the first non-assertion method is found.
   *
   * @return
   *   Array representing the true caller.
   */
  protected function getAssertionCall() {
    $backtrace = debug_backtrace();

    // The first element is the call. The second element is the caller.
    // We skip calls that occurred in one of the methods of our base classes
    // or in an assertion function.
   while (($caller = $backtrace[1]) &&
         ((isset($caller['class']) && isset($this->skipClasses[$caller['class']])) ||
           substr($caller['function'], 0, 6) == 'assert')) {
      // We remove that call.
      array_shift($backtrace);
    }

    return _drupal_get_last_caller($backtrace);
  }

  /**
   * Check to see if a value is not false.
   *
   * False values are: empty string, 0, NULL, and FALSE.
   *
   * @param $value
   *   The value on which the assertion is to be done.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertTrue($value, $message = '', $group = 'Other') {
    return $this->assert((bool) $value, $message ? $message : t('Value @value is TRUE.', array('@value' => var_export($value, TRUE))), $group);
  }

  /**
   * Check to see if a value is false.
   *
   * False values are: empty string, 0, NULL, and FALSE.
   *
   * @param $value
   *   The value on which the assertion is to be done.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertFalse($value, $message = '', $group = 'Other') {
    return $this->assert(!$value, $message ? $message : t('Value @value is FALSE.', array('@value' => var_export($value, TRUE))), $group);
  }

  /**
   * Check to see if a value is NULL.
   *
   * @param $value
   *   The value on which the assertion is to be done.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertNull($value, $message = '', $group = 'Other') {
    return $this->assert(!isset($value), $message ? $message : t('Value @value is NULL.', array('@value' => var_export($value, TRUE))), $group);
  }

  /**
   * Check to see if a value is not NULL.
   *
   * @param $value
   *   The value on which the assertion is to be done.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertNotNull($value, $message = '', $group = 'Other') {
    return $this->assert(isset($value), $message ? $message : t('Value @value is not NULL.', array('@value' => var_export($value, TRUE))), $group);
  }

  /**
   * Check to see if two values are equal.
   *
   * @param $first
   *   The first value to check.
   * @param $second
   *   The second value to check.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertEqual($first, $second, $message = '', $group = 'Other') {
    return $this->assert($first == $second, $message ? $message : t('Value @first is equal to value @second.', array('@first' => var_export($first, TRUE), '@second' => var_export($second, TRUE))), $group);
  }

  /**
   * Check to see if two values are not equal.
   *
   * @param $first
   *   The first value to check.
   * @param $second
   *   The second value to check.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertNotEqual($first, $second, $message = '', $group = 'Other') {
    return $this->assert($first != $second, $message ? $message : t('Value @first is not equal to value @second.', array('@first' => var_export($first, TRUE), '@second' => var_export($second, TRUE))), $group);
  }

  /**
   * Check to see if two values are identical.
   *
   * @param $first
   *   The first value to check.
   * @param $second
   *   The second value to check.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertIdentical($first, $second, $message = '', $group = 'Other') {
    return $this->assert($first === $second, $message ? $message : t('Value @first is identical to value @second.', array('@first' => var_export($first, TRUE), '@second' => var_export($second, TRUE))), $group);
  }

  /**
   * Check to see if two values are not identical.
   *
   * @param $first
   *   The first value to check.
   * @param $second
   *   The second value to check.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertNotIdentical($first, $second, $message = '', $group = 'Other') {
    return $this->assert($first !== $second, $message ? $message : t('Value @first is not identical to value @second.', array('@first' => var_export($first, TRUE), '@second' => var_export($second, TRUE))), $group);
  }

  /**
   * Checks to see if two objects are identical.
   *
   * @param object $object1
   *   The first object to check.
   * @param object $object2
   *   The second object to check.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertIdenticalObject($object1, $object2, $message = '', $group = 'Other') {
    $message = $message ?: format_string('!object1 is identical to !object2', array(
      '!object1' => var_export($object1, TRUE),
      '!object2' => var_export($object2, TRUE),
    ));
    $identical = TRUE;
    foreach ($object1 as $key => $value) {
      $identical = $identical && isset($object2->$key) && $object2->$key === $value;
    }
    return $this->assertTrue($identical, $message, $group);
  }



  /**
   * Fire an assertion that is always positive.
   *
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   TRUE.
   */
  protected function pass($message = NULL, $group = 'Other') {
    return $this->assert(TRUE, $message, $group);
  }

  /**
   * Fire an assertion that is always negative.
   *
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return
   *   FALSE.
   */
  protected function fail($message = NULL, $group = 'Other') {
    return $this->assert(FALSE, $message, $group);
  }

  /**
   * Fire an error assertion.
   *
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use format_string() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   * @param $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   * @param $caller
   *   The caller of the error.
   *
   * @return
   *   FALSE.
   */
  protected function error($message = '', $group = 'Other', array $caller = NULL) {
    if ($group == 'User notice') {
      // Since 'User notice' is set by trigger_error() which is used for debug
      // set the message to a status of 'debug'.
      return $this->assert('debug', $message, 'Debug', $caller);
    }

    return $this->assert('exception', $message, $group, $caller);
  }

  /**
   * Logs verbose message in a text file.
   *
   * The a link to the vebose message will be placed in the test results via
   * as a passing assertion with the text '[verbose message]'.
   *
   * @param $message
   *   The verbose message to be stored.
   *
   * @see simpletest_verbose()
   */
  protected function verbose($message) {
    // Do nothing if verbose debugging is disabled.
    if (!$this->verbose) {
      return;
    }

    $message = '<hr />ID #' . $this->verboseId . ' (<a href="' . $this->verboseClassName . '-' . ($this->verboseId - 1) . '.html">Previous</a> | <a href="' . $this->verboseClassName . '-' . ($this->verboseId + 1) . '.html">Next</a>)<hr />' . $message;
    $verbose_filename = $this->verboseDirectory . '/' . $this->verboseClassName . '-' . $this->verboseId . '.html';
    if (file_put_contents($verbose_filename, $message, FILE_APPEND)) {
      $url = $this->verboseDirectoryUrl . '/' . $this->verboseClassName . '-' . $this->verboseId . '.html';
      // Not using l() to avoid invoking the theme system, so that unit tests
      // can use verbose() as well.
      $url = '<a href="' . $url . '" target="_blank">' . t('Verbose message') . '</a>';
      $this->error($url, 'User notice');
    }
    $this->verboseId++;
  }

  /**
   * Run all tests in this class.
   *
   * Regardless of whether $methods are passed or not, only method names
   * starting with "test" are executed.
   *
   * @param $methods
   *   (optional) A list of method names in the test case class to run; e.g.,
   *   array('testFoo', 'testBar'). By default, all methods of the class are
   *   taken into account, but it can be useful to only run a few selected test
   *   methods during debugging.
   */
  public function run(array $methods = array()) {
    TestServiceProvider::$currentTest = $this;
    $simpletest_config = \Drupal::config('simpletest.settings');

    $class = get_class($this);
    if ($simpletest_config->get('verbose')) {
      // Initialize verbose debugging.
      $this->verbose = TRUE;
      $this->verboseDirectory = variable_get('file_public_path', conf_path() . '/files') . '/simpletest/verbose';
      $this->verboseDirectoryUrl = file_create_url($this->verboseDirectory);
      if (file_prepare_directory($this->verboseDirectory, FILE_CREATE_DIRECTORY) && !file_exists($this->verboseDirectory . '/.htaccess')) {
        file_put_contents($this->verboseDirectory . '/.htaccess', "<IfModule mod_expires.c>\nExpiresActive Off\n</IfModule>\n");
      }
      $this->verboseClassName = str_replace("\\", "_", $class);
    }
    // HTTP auth settings (<username>:<password>) for the simpletest browser
    // when sending requests to the test site.
    $this->httpauth_method = (int) $simpletest_config->get('httpauth.method');
    $username = $simpletest_config->get('httpauth.username');
    $password = $simpletest_config->get('httpauth.password');
    if (!empty($username) && !empty($password)) {
      $this->httpauth_credentials = $username . ':' . $password;
    }

    set_error_handler(array($this, 'errorHandler'));
    // Iterate through all the methods in this class, unless a specific list of
    // methods to run was passed.
    $class_methods = get_class_methods($class);
    if ($methods) {
      $class_methods = array_intersect($class_methods, $methods);
    }
    $missing_requirements = $this->checkRequirements();
    if (!empty($missing_requirements)) {
      $missing_requirements_object = new ReflectionObject($this);
      $caller = array(
        'file' => $missing_requirements_object->getFileName(),
      );
      foreach ($missing_requirements as $missing_requirement) {
        TestBase::insertAssert($this->testId, $class, FALSE, $missing_requirement, 'Requirements check.', $caller);
      }
    }
    else {
      foreach ($class_methods as $method) {
        // If the current method starts with "test", run it - it's a test.
        if (strtolower(substr($method, 0, 4)) == 'test') {
          // Insert a fail record. This will be deleted on completion to ensure
          // that testing completed.
          $method_info = new ReflectionMethod($class, $method);
          $caller = array(
            'file' => $method_info->getFileName(),
            'line' => $method_info->getStartLine(),
            'function' => $class . '->' . $method . '()',
          );
          $completion_check_id = TestBase::insertAssert($this->testId, $class, FALSE, t('The test did not complete due to a fatal error.'), 'Completion check', $caller);
          $this->setUp();
          if ($this->setup) {
            try {
              $this->$method();
              // Finish up.
            }
            catch (\Exception $e) {
              $this->exceptionHandler($e);
            }
            $this->tearDown();
          }
          else {
            $this->fail(t("The test cannot be executed because it has not been set up properly."));
          }
          // Remove the completion check record.
          TestBase::deleteAssert($completion_check_id);
        }
      }
    }
    TestServiceProvider::$currentTest = NULL;
    // Clear out the error messages and restore error handler.
    drupal_get_messages();
    restore_error_handler();
  }

  /**
   * Generates a database prefix for running tests.
   *
   * The database prefix is used by prepareEnvironment() to setup a public files
   * directory for the test to be run, which also contains the PHP error log,
   * which is written to in case of a fatal error. Since that directory is based
   * on the database prefix, all tests (even unit tests) need to have one, in
   * order to access and read the error log.
   *
   * @see TestBase::prepareEnvironment()
   *
   * The generated database table prefix is used for the Drupal installation
   * being performed for the test. It is also used as user agent HTTP header
   * value by the cURL-based browser of DrupalWebTestCase, which is sent to the
   * Drupal installation of the test. During early Drupal bootstrap, the user
   * agent HTTP header is parsed, and if it matches, all database queries use
   * the database table prefix that has been generated here.
   *
   * @see WebTestBase::curlInitialize()
   * @see drupal_valid_test_ua()
   * @see WebTestBase::setUp()
   */
  protected function prepareDatabasePrefix() {
    $this->databasePrefix = 'simpletest' . mt_rand(1000, 1000000);

    // As soon as the database prefix is set, the test might start to execute.
    // All assertions as well as the SimpleTest batch operations are associated
    // with the testId, so the database prefix has to be associated with it.
    db_update('simpletest_test_id')
      ->fields(array('last_prefix' => $this->databasePrefix))
      ->condition('test_id', $this->testId)
      ->execute();
  }

  /**
   * Changes the database connection to the prefixed one.
   *
   * @see WebTestBase::setUp()
   */
  protected function changeDatabasePrefix() {
    if (empty($this->databasePrefix)) {
      $this->prepareDatabasePrefix();
      // If $this->prepareDatabasePrefix() failed to work, return without
      // setting $this->setupDatabasePrefix to TRUE, so setUp() methods will
      // know to bail out.
      if (empty($this->databasePrefix)) {
        return;
      }
    }

    // Clone the current connection and replace the current prefix.
    $connection_info = Database::getConnectionInfo('default');
    Database::renameConnection('default', 'simpletest_original_default');
    foreach ($connection_info as $target => $value) {
      $connection_info[$target]['prefix'] = array(
        'default' => $value['prefix']['default'] . $this->databasePrefix,
      );
    }
    Database::addConnectionInfo('default', 'default', $connection_info['default']);

    // Additionally override global $databases, since the installer does not use
    // the Database connection info.
    // @see install_verify_database_settings()
    // @see install_database_errors()
    // @todo Fix installer to use Database connection info.
    global $databases;
    $databases['default']['default'] = $connection_info['default'];

    // Indicate the database prefix was set up correctly.
    $this->setupDatabasePrefix = TRUE;
  }

  /**
   * Prepares the current environment for running the test.
   *
   * Backups various current environment variables and resets them, so they do
   * not interfere with the Drupal site installation in which tests are executed
   * and can be restored in TestBase::tearDown().
   *
   * Also sets up new resources for the testing environment, such as the public
   * filesystem and configuration directories.
   *
   * @see TestBase::tearDown()
   */
  protected function prepareEnvironment() {
    global $user, $conf;
    $language_interface = language(Language::TYPE_INTERFACE);

    // When running the test runner within a test, back up the original database
    // prefix and re-set the new/nested prefix in drupal_valid_test_ua().
    if (drupal_valid_test_ua()) {
      $this->originalPrefix = drupal_valid_test_ua();
      drupal_valid_test_ua($this->databasePrefix);
    }

    // Backup current in-memory configuration.
    $this->originalSettings = settings()->getAll();
    $this->originalConf = $conf;

    // Backup statics and globals.
    $this->originalContainer = clone drupal_container();
    $this->originalLanguage = $language_interface;
    $this->originalConfigDirectories = $GLOBALS['config_directories'];
    if (isset($GLOBALS['theme_key'])) {
      $this->originalThemeKey = $GLOBALS['theme_key'];
    }
    $this->originalTheme = isset($GLOBALS['theme']) ? $GLOBALS['theme'] : NULL;

    // Save further contextual information.
    $this->originalFileDirectory = variable_get('file_public_path', conf_path() . '/files');
    $this->originalProfile = drupal_get_profile();
    $this->originalUser = isset($user) ? clone $user : NULL;

    // Ensure that the current session is not changed by the new environment.
    require_once DRUPAL_ROOT . '/' . settings()->get('session_inc', 'core/includes/session.inc');
    drupal_save_session(FALSE);
    // Run all tests as a anonymous user by default, web tests will replace that
    // during the test set up.
    $user = drupal_anonymous_user();

    // Save and clean the shutdown callbacks array because it is static cached
    // and will be changed by the test run. Otherwise it will contain callbacks
    // from both environments and the testing environment will try to call the
    // handlers defined by the original one.
    $callbacks = &drupal_register_shutdown_function();
    $this->originalShutdownCallbacks = $callbacks;
    $callbacks = array();

    // Create test directory ahead of installation so fatal errors and debug
    // information can be logged during installation process.
    // Use temporary files directory with the same prefix as the database.
    $this->public_files_directory = $this->originalFileDirectory . '/simpletest/' . substr($this->databasePrefix, 10);
    $this->private_files_directory = $this->public_files_directory . '/private';
    $this->temp_files_directory = $this->private_files_directory . '/temp';
    $this->translation_files_directory = $this->public_files_directory . '/translations';

    // Create the directories
    file_prepare_directory($this->public_files_directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
    file_prepare_directory($this->private_files_directory, FILE_CREATE_DIRECTORY);
    file_prepare_directory($this->temp_files_directory, FILE_CREATE_DIRECTORY);
    file_prepare_directory($this->translation_files_directory, FILE_CREATE_DIRECTORY);
    $this->generatedTestFiles = FALSE;

    // Create and set new configuration directories.
    $this->prepareConfigDirectories();

    // Reset statics before the old container is replaced so that objects with a
    // __destruct() method still have access to it.
    // @todo: Remove once they have been converted to services.
    drupal_static_reset();

    // Reset and create a new service container.
    $this->container = new ContainerBuilder();
     // @todo Remove this once this class has no calls to t() and format_plural()
    $this->container->register('string_translation', 'Drupal\Core\StringTranslation\TranslationManager');

    \Drupal::setContainer($this->container);

    // Unset globals.
    unset($GLOBALS['theme_key']);
    unset($GLOBALS['theme']);

    // Log fatal errors.
    ini_set('log_errors', 1);
    ini_set('error_log', $this->public_files_directory . '/error.log');

    // Set the test information for use in other parts of Drupal.
    $test_info = &$GLOBALS['drupal_test_info'];
    $test_info['test_run_id'] = $this->databasePrefix;
    $test_info['in_child_site'] = FALSE;

    // Indicate the environment was set up correctly.
    $this->setupEnvironment = TRUE;
  }

  /**
   * Create and set new configuration directories.
   *
   * The child site uses drupal_valid_test_ua() to adjust the config directory
   * paths to a test-prefix-specific directory within the public files
   * directory.
   *
   * @see config_get_config_directory()
   */
  protected function prepareConfigDirectories() {
    $GLOBALS['config_directories'] = array();
    $this->configDirectories = array();
    include_once DRUPAL_ROOT . '/core/includes/install.inc';
    foreach (array(CONFIG_ACTIVE_DIRECTORY, CONFIG_STAGING_DIRECTORY) as $type) {
      // Assign the relative path to the global variable.
      $path = 'simpletest/' . substr($this->databasePrefix, 10) . '/config_' . $type;
      $GLOBALS['config_directories'][$type]['path'] = $path;
      // Ensure the directory can be created and is writeable.
      if (!install_ensure_config_directory($type)) {
        return FALSE;
      }
      // Provide the already resolved path for tests.
      $this->configDirectories[$type] = $this->originalFileDirectory . '/' . $path;
    }
  }

  /**
   * Rebuild Drupal::getContainer().
   *
   * Use this to build a new kernel and service container. For example, when the
   * list of enabled modules is changed via the internal browser, in which case
   * the test process still contains an old kernel and service container with an
   * old module list.
   *
   * @see TestBase::prepareEnvironment()
   * @see TestBase::tearDown()
   *
   * @todo Fix http://drupal.org/node/1708692 so that module enable/disable
   *   changes are immediately reflected in Drupal::getContainer(). Until then,
   *   tests can invoke this workaround when requiring services from newly
   *   enabled modules to be immediately available in the same request.
   */
  protected function rebuildContainer() {
    $this->kernel = new DrupalKernel('testing', drupal_classloader(), FALSE);
    $this->kernel->boot();
    // DrupalKernel replaces the container in Drupal::getContainer() with a
    // different object, so we need to replace the instance on this test class.
    $this->container = \Drupal::getContainer();
    // The global $user is set in TestBase::prepareEnvironment().
    $this->container->get('request')->attributes->set('_account', $GLOBALS['user']);
  }

  /**
   * Deletes created files, database tables, and reverts environment changes.
   *
   * This method needs to be invoked for both unit and integration tests.
   *
   * @see TestBase::prepareDatabasePrefix()
   * @see TestBase::changeDatabasePrefix()
   * @see TestBase::prepareEnvironment()
   */
  protected function tearDown() {
    global $user, $conf;

    // Reset all static variables.
    // Unsetting static variables will potentially invoke destruct methods,
    // which might call into functions that prime statics and caches again.
    // In that case, all functions are still operating on the test environment,
    // which means they may need to access its filesystem and database.
    drupal_static_reset();

    // Ensure that TestBase::changeDatabasePrefix() has run and TestBase::$setup
    // was not tricked into TRUE, since the following code would delete the
    // entire parent site otherwise.
    if ($this->setupDatabasePrefix) {
      // Remove all prefixed tables.
      $connection_info = Database::getConnectionInfo('default');
      $tables = db_find_tables($connection_info['default']['prefix']['default'] . '%');
      $prefix_length = strlen($connection_info['default']['prefix']['default']);
      foreach ($tables as $table) {
        if (db_drop_table(substr($table, $prefix_length))) {
          unset($tables[$table]);
        }
      }
      if (!empty($tables)) {
        $this->fail('Failed to drop all prefixed tables.');
      }
    }

    // In case a fatal error occurred that was not in the test process read the
    // log to pick up any fatal errors.
    simpletest_log_read($this->testId, $this->databasePrefix, get_class($this), TRUE);
    if (($container = drupal_container()) && $container->has('keyvalue')) {
      $captured_emails = \Drupal::state()->get('system.test_email_collector') ?: array();
      $emailCount = count($captured_emails);
      if ($emailCount) {
        $message = format_plural($emailCount, '1 e-mail was sent during this test.', '@count e-mails were sent during this test.');
        $this->pass($message, t('E-mail'));
      }
    }

    // Delete temporary files directory.
    file_unmanaged_delete_recursive($this->originalFileDirectory . '/simpletest/' . substr($this->databasePrefix, 10), array($this, 'filePreDeleteCallback'));

    // Restore original database connection.
    Database::removeConnection('default');
    Database::renameConnection('simpletest_original_default', 'default');
    // @see TestBase::changeDatabasePrefix()
    global $databases;
    $connection_info = Database::getConnectionInfo('default');
    $databases['default']['default'] = $connection_info['default'];

    // Restore original globals.
    if (isset($this->originalThemeKey)) {
      $GLOBALS['theme_key'] = $this->originalThemeKey;
    }
    $GLOBALS['theme'] = $this->originalTheme;

    // Reset all static variables.
    // All destructors of statically cached objects have been invoked above;
    // this second reset is guranteed to reset everything to nothing.
    drupal_static_reset();

    // Restore original in-memory configuration.
    $conf = $this->originalConf;
    new Settings($this->originalSettings);

    // Restore original statics and globals.
    \Drupal::setContainer($this->originalContainer);
    $GLOBALS['config_directories'] = $this->originalConfigDirectories;
    if (isset($this->originalPrefix)) {
      drupal_valid_test_ua($this->originalPrefix);
    }

    // Restore original shutdown callbacks.
    $callbacks = &drupal_register_shutdown_function();
    $callbacks = $this->originalShutdownCallbacks;

    // Restore original user session.
    $user = $this->originalUser;
    drupal_save_session(TRUE);
  }

  /**
   * Handle errors during test runs.
   *
   * Because this is registered in set_error_handler(), it has to be public.
   *
   * @see set_error_handler
   */
  public function errorHandler($severity, $message, $file = NULL, $line = NULL) {
    if ($severity & error_reporting()) {
      require_once DRUPAL_ROOT . '/core/includes/errors.inc';
      $error_map = array(
        E_STRICT => 'Run-time notice',
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core error',
        E_CORE_WARNING => 'Core warning',
        E_USER_ERROR => 'User error',
        E_USER_WARNING => 'User warning',
        E_USER_NOTICE => 'User notice',
        E_RECOVERABLE_ERROR => 'Recoverable error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User deprecated',
      );

      $backtrace = debug_backtrace();

      // Add verbose backtrace for errors, but not for debug() messages.
      if ($severity !== E_USER_NOTICE) {
        $verbose_backtrace = $backtrace;
        array_shift($verbose_backtrace);
        $message .= '<pre class="backtrace">' . format_backtrace($verbose_backtrace) . '</pre>';
      }

      $this->error($message, $error_map[$severity], _drupal_get_last_caller($backtrace));
    }
    return TRUE;
  }

  /**
   * Handle exceptions.
   *
   * @see set_exception_handler
   */
  protected function exceptionHandler($exception) {
    require_once DRUPAL_ROOT . '/core/includes/errors.inc';
    $backtrace = $exception->getTrace();
    $verbose_backtrace = $backtrace;
    // Push on top of the backtrace the call that generated the exception.
    array_unshift($backtrace, array(
      'line' => $exception->getLine(),
      'file' => $exception->getFile(),
    ));
    // The exception message is run through check_plain()
    // by _drupal_decode_exception().
    $decoded_exception = _drupal_decode_exception($exception);
    unset($decoded_exception['backtrace']);
    $message = format_string('%type: !message in %function (line %line of %file). <pre class="backtrace">!backtrace</pre>', $decoded_exception + array(
      '!backtrace' => format_backtrace($verbose_backtrace),
    ));
    $this->error($message, 'Uncaught exception', _drupal_get_last_caller($backtrace));
  }

  /**
   * Changes in memory settings.
   *
   * @param $name
   *   The name of the setting to return.
   * @param $value
   *   The value of the setting.
   *
   * @see \Drupal\Component\Utility\Settings::get()
   */
  protected function settingsSet($name, $value) {
    $settings = settings()->getAll();
    $settings[$name] = $value;
    new Settings($settings);
  }

  /**
   * Generates a unique random string of ASCII characters of codes 32 to 126.
   *
   * Do not use this method when special characters are not possible (e.g., in
   * machine or file names that have already been validated); instead, use
   * \Drupal\simpletest\TestBase::randomName().
   *
   * @param int $length
   *   Length of random string to generate.
   *
   * @return string
   *   Randomly generated unique string.
   *
   * @see \Drupal\Component\Utility\Random::string()
   */
  public function randomString($length = 8) {
    return Random::string($length, TRUE);
  }

  /**
   * Generates a unique random string containing letters and numbers.
   *
   * Do not use this method when testing unvalidated user input. Instead, use
   * \Drupal\simpletest\TestBase::randomString().
   *
   * @param int $length
   *   Length of random string to generate.
   *
   * @return string
   *   Randomly generated unique string.
   *
   * @see \Drupal\Component\Utility\Random::name()
   */
  public function randomName($length = 8) {
    return Random::name($length, TRUE);
  }

  /**
   * Generates a random PHP object.
   *
   * @param int $size
   *   The number of random keys to add to the object.
   *
   * @return \stdClass
   *   The generated object, with the specified number of random keys. Each key
   *   has a random string value.
   *
   * @see \Drupal\Component\Utility\Random::object()
   */
  public function randomObject($size = 4) {
    return Random::object($size);
  }

  /**
   * Converts a list of possible parameters into a stack of permutations.
   *
   * Takes a list of parameters containing possible values, and converts all of
   * them into a list of items containing every possible permutation.
   *
   * Example:
   * @code
   * $parameters = array(
   *   'one' => array(0, 1),
   *   'two' => array(2, 3),
   * );
   * $permutations = TestBase::generatePermutations($parameters);
   * // Result:
   * $permutations == array(
   *   array('one' => 0, 'two' => 2),
   *   array('one' => 1, 'two' => 2),
   *   array('one' => 0, 'two' => 3),
   *   array('one' => 1, 'two' => 3),
   * )
   * @endcode
   *
   * @param $parameters
   *   An associative array of parameters, keyed by parameter name, and whose
   *   values are arrays of parameter values.
   *
   * @return
   *   A list of permutations, which is an array of arrays. Each inner array
   *   contains the full list of parameters that have been passed, but with a
   *   single value only.
   */
  public static function generatePermutations($parameters) {
    $all_permutations = array(array());
    foreach ($parameters as $parameter => $values) {
      $new_permutations = array();
      // Iterate over all values of the parameter.
      foreach ($values as $value) {
        // Iterate over all existing permutations.
        foreach ($all_permutations as $permutation) {
          // Add the new parameter value to existing permutations.
          $new_permutations[] = $permutation + array($parameter => $value);
        }
      }
      // Replace the old permutations with the new permutations.
      $all_permutations = $new_permutations;
    }
    return $all_permutations;
  }

  /**
   * Ensures test files are deletable within file_unmanaged_delete_recursive().
   *
   * Some tests chmod generated files to be read only. During tearDown() and
   * other cleanup operations, these files need to get deleted too.
   */
  public static function filePreDeleteCallback($path) {
    chmod($path, 0700);
  }

  /**
   * Returns a ConfigImporter object to import test importing of configuration.
   *
   * @return \Drupal\Core\Config\ConfigImporter
   *   The ConfigImporter object.
   */
  public function configImporter() {
    if (!$this->configImporter) {
      // Set up the ConfigImporter object for testing.
      $config_comparer = new StorageComparer(
        $this->container->get('config.storage.staging'),
        $this->container->get('config.storage')
      );
      $this->configImporter = new ConfigImporter(
        $config_comparer,
        $this->container->get('event_dispatcher'),
        $this->container->get('config.factory'),
        $this->container->get('plugin.manager.entity'),
        $this->container->get('lock')
      );
    }
    // Always recalculate the changelist when called.
    return $this->configImporter->reset();
  }

  /**
   * Copies configuration objects from source storage to target storage.
   *
   * @param \Drupal\Core\Config\StorageInterface $source_storage
   *   The source config storage service.
   * @param \Drupal\Core\Config\StorageInterface $target_storage
   *   The target config storage service.
   */
  public function copyConfig(StorageInterface $source_storage, StorageInterface $target_storage) {
    $target_storage->deleteAll();
    foreach ($source_storage->listAll() as $name) {
      $target_storage->write($name, $source_storage->read($name));
    }
  }
}
