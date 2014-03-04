<?php

/**
 * @file
 * Definition of Drupal\system\Tests\ServiceProvider\ServiceProviderTest.
 */

namespace Drupal\system\Tests\ServiceProvider;

use Drupal\simpletest\WebTestBase;

/**
 * Tests service provider registration to the DIC.
 */
class ServiceProviderTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('file', 'service_provider_test');

  public static function getInfo() {
    return array(
      'name' => 'Service Provider Registration',
      'description' => 'Tests service provider registration to the DIC.',
      'group' => 'Service Provider',
    );
  }

  /**
   * Tests that services provided by module service providers get registered to the DIC.
   */
  function testServiceProviderRegistration() {
    $this->assertTrue(drupal_container()->getDefinition('file.usage')->getClass() == 'Drupal\\service_provider_test\\TestFileUsage', 'Class has been changed');
    $this->assertTrue(drupal_container()->has('service_provider_test_class'), 'The service_provider_test_class service has been registered to the DIC');
    // The event subscriber method in the test class calls drupal_set_message with
    // a message saying it has fired. This will fire on every page request so it
    // should show up on the front page.
    $this->drupalGet('');
    $this->assertText(t('The service_provider_test event subscriber fired!'), 'The service_provider_test event subscriber fired');
  }

  /**
   * Tests that the DIC keeps up with module enable/disable in the same request.
   */
  function testServiceProviderRegistrationDynamic() {
    // Disable the module and ensure the service provider's service is not registered.
    module_disable(array('service_provider_test'));
    $this->assertFalse(drupal_container()->has('service_provider_test_class'), 'The service_provider_test_class service does not exist in the DIC.');

    // Enable the module and ensure the service provider's service is registered.
    module_enable(array('service_provider_test'));
    $this->assertTrue(drupal_container()->has('service_provider_test_class'), 'The service_provider_test_class service exists in the DIC.');
  }

}
