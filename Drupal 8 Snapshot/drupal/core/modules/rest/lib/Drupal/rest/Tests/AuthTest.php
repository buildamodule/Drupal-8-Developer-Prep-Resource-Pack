<?php

/**
 * @file
 * Definition of Drupal\rest\test\AuthTest.
 */

namespace Drupal\rest\Tests;

use Drupal\rest\Tests\RESTTestBase;

/**
 * Tests authenticated operations on test entities.
 */
class AuthTest extends RESTTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('hal', 'rest', 'entity_test');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Resource authentication',
      'description' => 'Tests authentication provider restrictions.',
      'group' => 'REST',
    );
  }

  /**
   * Tests reading from an authenticated resource.
   */
  public function testRead() {
    $entity_type = 'entity_test';

    // Enable a test resource through GET method and basic HTTP authentication.
    $this->enableService('entity:' . $entity_type, 'GET', NULL, array('http_basic'));

    // Create an entity programmatically.
    $entity = $this->entityCreate($entity_type);
    $entity->save();

    // Try to read the resource as an anonymous user, which should not work.
    $response = $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'GET', NULL, $this->defaultMimeType);
    $this->assertResponse('401', 'HTTP response code is 401 when the request is not authenticated and the user is anonymous.');
    $this->assertText('A fatal error occurred: No authentication credentials provided.');

    // Create a user account that has the required permissions to read
    // resources via the REST API, but the request is authenticated
    // with session cookies.
    $permissions = $this->entityPermissions($entity_type, 'view');
    $permissions[] = 'restful get entity:' . $entity_type;
    $account = $this->drupalCreateUser($permissions);
    $this->drupalLogin($account);

    // Try to read the resource with session cookie authentication, which is
    // not enabled and should not work.
    $response = $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'GET', NULL, $this->defaultMimeType);
    $this->assertResponse('403', 'HTTP response code is 403 when the request is authenticated but not authorized.');
    $this->drupalLogout();

    // Now read it with the Basic authentication which is enabled and should
    // work.
    $response = $this->basicAuthGet('entity/' . $entity_type . '/' . $entity->id(), $account->getUsername(), $account->pass_raw);
    $this->assertResponse('200', 'HTTP response code is 200 for successfuly authorized requests.');
    $this->curlClose();
  }

  /**
   * Performs a HTTP request with Basic authentication.
   *
   * We do not use \Drupal\simpletest\WebTestBase::drupalGet because we need to
   * set curl settings for basic authentication.
   *
   * @param string $path
   *   The request path.
   * @param string $username
   *   The user name to authenticate with.
   * @param string $password
   *   The password.
   *
   * @return string
   *   Curl output.
   */
  protected function basicAuthGet($path, $username, $password) {
    $out = $this->curlExec(
      array(
        CURLOPT_HTTPGET => TRUE,
        CURLOPT_URL => url($path, array('absolute' => TRUE)),
        CURLOPT_NOBODY => FALSE,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $username . ':' . $password,
      )
    );

    $this->verbose('GET request to: ' . $path .
      '<hr />' . $out);

    return $out;
  }

}
