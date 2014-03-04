<?php

/**
 * @file
 * Contains \Drupal\field\Tests\Views\FieldTestBase.
 */

/**
 * @TODO
 *   - Test on a generic entity not on a node.
 *
 * What has to be tested:
 *   - Take sure that every wanted field is added to the according entity type.
 *   - Take sure the joins are done correct.
 *   - Use basic fields and take sure that the full wanted object is build.
 *   - Use relationships between different entity types, for example node and the node author(user).
 */

namespace Drupal\field\Tests\Views;

use Drupal\views\Tests\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Provides some helper methods for testing fieldapi integration into views.
 */
abstract class FieldTestBase extends ViewTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('field_test_views');

  /**
   * Stores the field definitions used by the test.
   *
   * @var array
   */
  public $fields;

  /**
   * Stores the instances of the fields. They have
   * the same keys as the fields.
   *
   * @var array
   */
  public $instances;

  protected function setUp() {
    parent::setUp();

    ViewTestData::importTestViews(get_class($this), array('field_test_views'));
  }

  function setUpFields($amount = 3) {
    // Create three fields.
    $field_names = array();
    for ($i = 0; $i < $amount; $i++) {
      $field_names[$i] = 'field_name_' . $i;
      $field = array('field_name' => $field_names[$i], 'type' => 'text');

      $this->fields[$i] = $field = entity_create('field_entity', $field);
      $field->save();
    }
    return $field_names;
  }

  function setUpInstances($bundle = 'page') {
    foreach ($this->fields as $key => $field) {
      $instance = array(
        'field_name' => $field['field_name'],
        'entity_type' => 'node',
        'bundle' => 'page',
      );
      $this->instances[$key] = entity_create('field_instance', $instance);
      $this->instances[$key]->save();
    }
  }

}
