<?php
/**
 * @file
 * Contains \Drupal\serialization\Tests\EntityResolverTest.
 */

namespace Drupal\serialization\Tests;

class EntityResolverTest extends NormalizerTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('entity_reference', 'hal', 'rest');

  /**
   * The format being tested.
   *
   * @var string
   */
  protected $format = 'hal_json';

  public static function getInfo() {
    return array(
      'name' => 'Entity resolver tests',
      'description' => 'Tests that entities references can be resolved.',
      'group' => 'Serialization',
    );
  }

  protected function setUp() {
    parent::setUp();

    // Create the test field.
    entity_create('field_entity', array(
      'settings' => array(
        'target_type' => 'entity_test_mulrev',
      ),
      'field_name' => 'field_test_entity_reference',
      'type' => 'entity_reference',
    ))->save();

    // Create the test field instance.
    entity_create('field_instance', array(
      'entity_type' => 'entity_test_mulrev',
      'field_name' => 'field_test_entity_reference',
      'bundle' => 'entity_test_mulrev',
    ))->save();
  }

  /**
   * Test that fields referencing UUIDs can be denormalized.
   */
  function testUuidEntityResolver() {
    // Create an entity to get the UUID from.
    $entity = entity_create('entity_test_mulrev', array('type' => 'entity_test_mulrev'));
    $entity->set('name', 'foobar');
    $entity->set('field_test_entity_reference', array(array('target_id' => 1)));
    $entity->save();

    $field_uri = url('rest/relation/entity_test_mulrev/entity_test_mulrev/field_test_entity_reference', array('absolute' => TRUE));

    $data = array(
      '_links' => array(
        'type' => array(
          'href' => url('rest/type/entity_test_mulrev/entity_test_mulrev', array('absolute' => TRUE)),
        ),
        $field_uri => array(
          array(
            'href' => url('entity/entity_test_mulrev/' . $entity->id()),
          ),
        ),
      ),
      '_embedded' => array(
        $field_uri => array(
          array(
            '_links' => array(
              'self' => url('entity/entity_test_mulrev/' . $entity->id()),
            ),
            'uuid' => array(
              array(
                'value' => $entity->uuid(),
              ),
            ),
          ),
        ),
      ),
    );

    $denormalized = $this->container->get('serializer')->denormalize($data, 'Drupal\entity_test\Plugin\Core\Entity\EntityTestMulRev', $this->format);
    $field_value = $denormalized->get('field_test_entity_reference')->getValue();
    $this->assertEqual($field_value[0]['target_id'], 1, 'Entity reference resolved using UUID.');
  }

}
