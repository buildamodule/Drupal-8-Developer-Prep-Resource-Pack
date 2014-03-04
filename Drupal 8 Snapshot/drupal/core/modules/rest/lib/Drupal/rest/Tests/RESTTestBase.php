<?php

/**
 * @file
 * Definition of Drupal\rest\test\RESTTestBase.
 */

namespace Drupal\rest\Tests;

use Drupal\Core\Session\AccountInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Test helper class that provides a REST client method to send HTTP requests.
 */
abstract class RESTTestBase extends WebTestBase {

  /**
   * The default serialization format to use for testing REST operations.
   *
   * @var string
   */
  protected $defaultFormat;

  /**
   * The default MIME type to use for testing REST operations.
   *
   * @var string
   */
  protected $defaultMimeType;

  protected function setUp() {
    parent::setUp();
    $this->defaultFormat = 'hal_json';
    $this->defaultMimeType = 'application/hal+json';
    // Create a test content type for node testing.
    $this->drupalCreateContentType(array('name' => 'resttest', 'type' => 'resttest'));
  }

  /**
   * Helper function to issue a HTTP request with simpletest's cURL.
   *
   * @param string $url
   *   The relative URL, e.g. "entity/node/1"
   * @param string $method
   *   HTTP method, one of GET, POST, PUT or DELETE.
   * @param array $body
   *   Either the body for POST and PUT or additional URL parameters for GET.
   * @param string $mime_type
   *   The MIME type of the transmitted content.
   */
  protected function httpRequest($url, $method, $body = NULL, $mime_type = NULL) {
    if (!isset($mime_type)) {
      $mime_type = $this->defaultMimeType;
    }
    if (!in_array($method, array('GET', 'HEAD', 'OPTIONS', 'TRACE'))) {
      // GET the CSRF token first for writing requests.
      $token = $this->drupalGet('rest/session/token');
    }
    switch ($method) {
      case 'GET':
        // Set query if there are additional GET parameters.
        $options = isset($body) ? array('absolute' => TRUE, 'query' => $body) : array('absolute' => TRUE);
        $curl_options = array(
          CURLOPT_HTTPGET => TRUE,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_URL => url($url, $options),
          CURLOPT_NOBODY => FALSE,
          CURLOPT_HTTPHEADER => array('Accept: ' . $mime_type),
        );
        break;

      case 'POST':
        $curl_options = array(
          CURLOPT_HTTPGET => FALSE,
          CURLOPT_POST => TRUE,
          CURLOPT_POSTFIELDS => $body,
          CURLOPT_URL => url($url, array('absolute' => TRUE)),
          CURLOPT_NOBODY => FALSE,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: ' . $mime_type,
            'X-CSRF-Token: ' . $token,
          ),
        );
        break;

      case 'PUT':
        $curl_options = array(
          CURLOPT_HTTPGET => FALSE,
          CURLOPT_CUSTOMREQUEST => 'PUT',
          CURLOPT_POSTFIELDS => $body,
          CURLOPT_URL => url($url, array('absolute' => TRUE)),
          CURLOPT_NOBODY => FALSE,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: ' . $mime_type,
            'X-CSRF-Token: ' . $token,
          ),
        );
        break;

      case 'PATCH':
        $curl_options = array(
          CURLOPT_HTTPGET => FALSE,
          CURLOPT_CUSTOMREQUEST => 'PATCH',
          CURLOPT_POSTFIELDS => $body,
          CURLOPT_URL => url($url, array('absolute' => TRUE)),
          CURLOPT_NOBODY => FALSE,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: ' . $mime_type,
            'X-CSRF-Token: ' . $token,
          ),
        );
        break;

      case 'DELETE':
        $curl_options = array(
          CURLOPT_HTTPGET => FALSE,
          CURLOPT_CUSTOMREQUEST => 'DELETE',
          CURLOPT_URL => url($url, array('absolute' => TRUE)),
          CURLOPT_NOBODY => FALSE,
          CURLOPT_HTTPHEADER => array('X-CSRF-Token: ' . $token),
        );
        break;
    }

    $response = $this->curlExec($curl_options);
    $headers = $this->drupalGetHeaders();
    $headers = implode("\n", $headers);

    $this->verbose($method . ' request to: ' . $url .
      '<hr />Code: ' . curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE) .
      '<hr />Response headers: ' . $headers .
      '<hr />Response body: ' . $response);

    return $response;
  }

  /**
   * Creates entity objects based on their types.
   *
   * @param string $entity_type
   *   The type of the entity that should be created.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The new entity object.
   */
  protected function entityCreate($entity_type) {
    return entity_create($entity_type, $this->entityValues($entity_type));
  }

  /**
   * Provides an array of suitable property values for an entity type.
   *
   * Required properties differ from entity type to entity type, so we keep a
   * minimum mapping here.
   *
   * @param string $entity_type
   *   The type of the entity that should be created.
   *
   * @return array
   *   An array of values keyed by property name.
   */
  protected function entityValues($entity_type) {
    switch ($entity_type) {
      case 'entity_test':
        return array(
          'name' => $this->randomName(),
          'user_id' => 1,
          'field_test_text' => array(0 => array('value' => $this->randomString())),
        );
      case 'node':
        return array('title' => $this->randomString(), 'type' => 'resttest');
      case 'node_type':
        return array(
          'type' => 'article',
          'name' => $this->randomName(),
        );
      case 'user':
        return array('name' => $this->randomName());
      default:
        return array();
    }
  }

  /**
   * Enables the REST service interface for a specific entity type.
   *
   * @param string|FALSE $resource_type
   *   The resource type that should get REST API enabled or FALSE to disable all
   *   resource types.
   * @param string $method
   *   The HTTP method to enable, e.g. GET, POST etc.
   * @param string $format
   *   (Optional) The serialization format, e.g. hal_json.
   * @param array $auth
   *   (Optional) The list of valid authentication methods.
   */
  protected function enableService($resource_type, $method = 'GET', $format = NULL, $auth = array()) {
    // Enable REST API for this entity type.
    $config = \Drupal::config('rest.settings');
    $settings = array();
    if ($resource_type) {
      if ($format) {
        $settings[$resource_type][$method]['supported_formats'][] = $format;
      }
      else {
        $settings[$resource_type][$method] = array();
      }
    }
    if (is_array($auth) && !empty($auth)) {
      $settings[$resource_type][$method]['supported_auth'] = $auth;
    }

    $config->set('resources', $settings);
    $config->save();

    // Rebuild routing cache, so that the REST API paths are available.
    $this->container->get('router.builder')->rebuild();
    // Reset the Simpletest permission cache, so that the new resource
    // permissions get picked up.
    drupal_static_reset('checkPermissions');
  }

  /**
   * Check if a HTTP response header exists and has the expected value.
   *
   * @param string $header
   *   The header key, example: Content-Type
   * @param string $value
   *   The header value.
   * @param string $message
   *   (optional) A message to display with the assertion.
   * @param string $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return bool
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertHeader($header, $value, $message = '', $group = 'Browser') {
    $header_value = $this->drupalGetHeader($header);
    return $this->assertTrue($header_value == $value, $message ? $message : 'HTTP response header ' . $header . ' with value ' . $value . ' found.', $group);
  }

  /**
   * Overrides WebTestBase::drupalLogin().
   */
  protected function drupalLogin(AccountInterface $user) {
    if (isset($this->curlHandle)) {
      // cURL quirk: when setting CURLOPT_CUSTOMREQUEST to anything other than
      // POST in httpRequest() it has to be restored to POST here. Otherwise the
      // POST request to login a user will not work.
      curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'POST');
    }
    parent::drupalLogin($user);
  }

  /**
   * Provides the necessary user permissions for entity operations.
   *
   * @param string $entity_type
   *   The entity type.
   * @param type $operation
   *   The operation, one of 'view', 'create', 'update' or 'delete'.
   *
   * @return array
   *   The set of user permission strings.
   */
  protected function entityPermissions($entity_type, $operation) {
    switch ($entity_type) {
      case 'entity_test':
        switch ($operation) {
          case 'view':
            return array('view test entity');
          case 'create':
          case 'update':
          case 'delete':
            return array('administer entity_test content');
        }
      case 'node':
        switch ($operation) {
          case 'view':
            return array('access content');
          case 'create':
            return array('create resttest content');
          case 'update':
            return array('edit any resttest content');
          case 'delete':
            return array('delete any resttest content');
        }
    }
  }
}
