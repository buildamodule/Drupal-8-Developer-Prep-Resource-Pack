<?php

/**
 * @file
 * Contains Drupal\rest\test\UpdateTest.
 */

namespace Drupal\rest\Tests;

use Drupal\rest\Tests\RESTTestBase;

/**
 * Tests resource updates on test entities.
 */
class UpdateTest extends RESTTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('hal', 'rest', 'entity_test');

  public static function getInfo() {
    return array(
      'name' => 'Update resource',
      'description' => 'Tests the update of resources.',
      'group' => 'REST',
    );
  }

  /**
   * Tests several valid and invalid partial update requests on test entities.
   */
  public function testPatchUpdate() {
    $serializer = $this->container->get('serializer');
    // @todo once EntityNG is implemented for other entity types test all other
    // entity types here as well.
    $entity_type = 'entity_test';

    $this->enableService('entity:' . $entity_type, 'PATCH');
    // Create a user account that has the required permissions to create
    // resources via the REST API.
    $permissions = $this->entityPermissions($entity_type, 'update');
    $permissions[] = 'restful patch entity:' . $entity_type;
    $account = $this->drupalCreateUser($permissions);
    $this->drupalLogin($account);

    // Create an entity and save it to the database.
    $entity = $this->entityCreate($entity_type);
    $entity->save();

    // Create a second stub entity for overwriting a field.
    $patch_values['field_test_text'] = array(0 => array('value' => $this->randomString()));
    $patch_entity = entity_create($entity_type, $patch_values);
    // We don't want to overwrite the UUID.
    unset($patch_entity->uuid);
    $serialized = $serializer->serialize($patch_entity, $this->defaultFormat);

    // Update the entity over the REST API.
    $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'PATCH', $serialized, $this->defaultMimeType);
    $this->assertResponse(204);

    // Re-load updated entity from the database.
    $entity = entity_load($entity_type, $entity->id(), TRUE);
    $this->assertEqual($entity->field_test_text->value, $patch_entity->field_test_text->value, 'Field was successfully updated.');

    // Make sure that the field does not get deleted if it is not present in the
    // PATCH request.
    $normalized = $serializer->normalize($patch_entity, $this->defaultFormat);
    unset($normalized['field_test_text']);
    $serialized = $serializer->encode($normalized, $this->defaultFormat);
    $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'PATCH', $serialized, $this->defaultMimeType);
    $this->assertResponse(204);

    $entity = entity_load($entity_type, $entity->id(), TRUE);
    $this->assertNotNull($entity->field_test_text->value. 'Test field has not been deleted.');

    // Try to empty a field.
    $normalized['field_test_text'] = array();
    $serialized = $serializer->encode($normalized, $this->defaultFormat);

    // Update the entity over the REST API.
    $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'PATCH', $serialized, $this->defaultMimeType);
    $this->assertResponse(204);

    // Re-load updated entity from the database.
    $entity = entity_load($entity_type, $entity->id(), TRUE);
    $this->assertNull($entity->field_test_text->value, 'Test field has been cleared.');

    // Enable access protection for the text field.
    // @see entity_test_entity_field_access()
    $entity->field_test_text->value = 'no access value';
    $entity->save();

    // Try to empty a field that is access protected.
    $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'PATCH', $serialized, $this->defaultMimeType);
    $this->assertResponse(403);

    // Re-load the entity from the database.
    $entity = entity_load($entity_type, $entity->id(), TRUE);
    $this->assertEqual($entity->field_test_text->value, 'no access value', 'Text field was not updated.');

    // Try to update an access protected field.
    $serialized = $serializer->serialize($patch_entity, $this->defaultFormat);
    $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'PATCH', $serialized, $this->defaultMimeType);
    $this->assertResponse(403);

    // Re-load the entity from the database.
    $entity = entity_load($entity_type, $entity->id(), TRUE);
    $this->assertEqual($entity->field_test_text->value, 'no access value', 'Text field was not updated.');

    // Restore the valid test value.
    $entity->field_test_text->value = $this->randomString();
    $entity->save();

    // Try to send no data at all, which does not make sense on PATCH requests.
    $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'PATCH', NULL, $this->defaultMimeType);
    $this->assertResponse(400);

    // Try to update a non-existing entity with ID 9999.
    $this->httpRequest('entity/' . $entity_type . '/9999', 'PATCH', $serialized, $this->defaultMimeType);
    $this->assertResponse(404);
    $loaded_entity = entity_load($entity_type, 9999, TRUE);
    $this->assertFalse($loaded_entity, 'Entity 9999 was not created.');

    // Try to send invalid data to trigger the entity validation constraints.
    // Send a UUID that is too long.
    $entity->set('uuid', $this->randomName(129));
    $invalid_serialized = $serializer->serialize($entity, $this->defaultFormat);
    $response = $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'PATCH', $invalid_serialized, $this->defaultMimeType);
    $this->assertResponse(422);
    $error = drupal_json_decode($response);
    $this->assertEqual($error['error'], "Unprocessable Entity: validation failed.\nuuid.0.value: This value is too long. It should have <em class=\"placeholder\">128</em> characters or less.\n");

    // Try to update an entity without proper permissions.
    $this->drupalLogout();
    $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'PATCH', $serialized, $this->defaultMimeType);
    $this->assertResponse(403);

    // Try to update a resource which is not REST API enabled.
    $this->enableService(FALSE);
    $this->drupalLogin($account);
    $this->httpRequest('entity/' . $entity_type . '/' . $entity->id(), 'PATCH', $serialized, $this->defaultMimeType);
    $this->assertResponse(404);
  }
}
