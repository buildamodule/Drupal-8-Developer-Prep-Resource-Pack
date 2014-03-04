<?php

/**
 * @file
 * Definition of Drupal\system\Tests\System\SystemAuthorizeTest.
 */

namespace Drupal\system\Tests\System;

use Drupal\simpletest\WebTestBase;

/**
 * Tests authorize.php and related hooks.
 */
class SystemAuthorizeTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('system_test');

  public static function getInfo() {
    return array(
      'name' => 'Authorize API',
      'description' => 'Tests the authorize.php script and related API.',
      'group' => 'System',
    );
  }

  function setUp() {
    parent::setUp();

    // Create an administrator user.
    $this->admin_user = $this->drupalCreateUser(array('administer software updates'));
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Helper function to initialize authorize.php and load it via drupalGet().
   *
   * Initializing authorize.php needs to happen in the child Drupal
   * installation, not the parent. So, we visit a menu callback provided by
   * system_test.module which calls system_authorized_init() to initialize the
   * $_SESSION inside the test site, not the framework site. This callback
   * redirects to authorize.php when it's done initializing.
   *
   * @see system_authorized_init().
   */
  function drupalGetAuthorizePHP($page_title = 'system-test-auth') {
    $this->drupalGet('system-test/authorize-init/' . $page_title);
  }

  /**
   * Tests the FileTransfer hooks
   */
  function testFileTransferHooks() {
    $page_title = $this->randomName(16);
    $this->drupalGetAuthorizePHP($page_title);
    $this->assertTitle(strtr('@title | Drupal', array('@title' => $page_title)), 'authorize.php page title is correct.');
    $this->assertNoText('It appears you have reached this page in error.');
    $this->assertText('To continue, provide your server connection details');
    // Make sure we see the new connection method added by system_test.
    $this->assertRaw('System Test FileTransfer');
    // Make sure the settings form callback works.
    $this->assertText('System Test Username');
  }
}
