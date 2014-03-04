<?php

/**
 * @file
 * Contains \Drupal\options\Tests\OptionsFieldUnitTestBase.
 */


namespace Drupal\options\Tests;

use Drupal\field\Tests\FieldUnitTestBase;

/**
 * Defines a common base test class for unit tests of the options module.
 */
class OptionsFieldUnitTestBase extends FieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('options');

  /**
   * The field name used in the test.
   *
   * @var string
   */
  protected $fieldName = 'test_options';

  /**
   * The field definition used to created the field entity.
   *
   * @var array
   */
  protected $fieldDefinition;

  /**
   * The list field used in the test.
   *
   * @var \Drupal\field\Plugin\Core\Entity\Field
   */
  protected $field;

  /**
   * The list field instance used in the test.
   *
   * @var \Drupal\field\Plugin\Core\Entity\FieldInstance
   */
  protected $instance;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('system', 'menu_router');

    $this->fieldDefinition = array(
      'field_name' => $this->fieldName,
      'type' => 'list_integer',
      'cardinality' => 1,
      'settings' => array(
        'allowed_values' => array(1 => 'One', 2 => 'Two', 3 => 'Three'),
      ),
    );
    $this->field = entity_create('field_entity', $this->fieldDefinition);
    $this->field->save();

    $instance = array(
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    );
    $this->instance = entity_create('field_instance', $instance);
    $this->instance->save();

    entity_get_form_display('entity_test', 'entity_test', 'default')
      ->setComponent($this->fieldName, array(
        'type' => 'options_buttons',
      ))
      ->save();
  }

}
