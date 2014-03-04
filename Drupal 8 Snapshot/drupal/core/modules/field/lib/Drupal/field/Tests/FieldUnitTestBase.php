<?php

/**
 * @file
 * Contains \Drupal\field\Tests\FieldUnitTestBase.
 */

namespace Drupal\field\Tests;

use Drupal\Core\Entity\EntityInterface;
use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Parent class for Field API unit tests.
 */
abstract class FieldUnitTestBase extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('user', 'entity', 'system', 'field', 'text', 'field_sql_storage', 'entity_test', 'field_test');

  /**
   * A string for assert raw and text helper methods.
   *
   * @var string
   */
  protected $content;

  /**
   * Set the default field storage backend for fields created during tests.
   */
  function setUp() {
    parent::setUp();
    $this->installSchema('system', array('sequences', 'variable', 'config_snapshot'));
    $this->installSchema('entity_test', 'entity_test');

    // Set default storage backend and configure the theme system.
    $this->installConfig(array('field', 'system'));
  }

  /**
   * Create a field and an instance of it.
   *
   * @param string $suffix
   *   (optional) A string that should only contain characters that are valid in
   *   PHP variable names as well.
   * @param string $entity_type
   *   (optional) The entity type on which the instance should be created.
   *   Defaults to "entity_test".
   * @param string $bundle
   *   (optional) The entity type on which the instance should be created.
   *   Defaults to the default bundle of the entity type.
   */
  function createFieldWithInstance($suffix = '', $entity_type = 'entity_test', $bundle = NULL) {
    if (empty($bundle)) {
      $bundle = $entity_type;
    }
    $field_name = 'field_name' . $suffix;
    $field = 'field' . $suffix;
    $field_id = 'field_id' . $suffix;
    $instance = 'instance' . $suffix;
    $instance_definition = 'instance_definition' . $suffix;

    $this->$field_name = drupal_strtolower($this->randomName() . '_field_name' . $suffix);
    $this->$field = entity_create('field_entity', array('field_name' => $this->$field_name, 'type' => 'test_field', 'cardinality' => 4));
    $this->$field->save();
    $this->$field_id = $this->{$field}['uuid'];
    $this->$instance_definition = array(
      'field_name' => $this->$field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $this->randomName() . '_label',
      'description' => $this->randomName() . '_description',
      'settings' => array(
        'test_instance_setting' => $this->randomName(),
      ),
    );
    $this->$instance = entity_create('field_instance', $this->$instance_definition);
    $this->$instance->save();

    entity_get_form_display($entity_type, $bundle, 'default')
      ->setComponent($this->$field_name, array(
        'type' => 'test_field_widget',
        'settings' => array(
          'test_widget_setting' => $this->randomName(),
        )
      ))
      ->save();
  }

  /**
   * Generate random values for a field_test field.
   *
   * @param $cardinality
   *   Number of values to generate.
   * @return
   *  An array of random values, in the format expected for field values.
   */
  function _generateTestFieldValues($cardinality) {
    $values = array();
    for ($i = 0; $i < $cardinality; $i++) {
      // field_test fields treat 0 as 'empty value'.
      $values[$i]['value'] = mt_rand(1, 127);
    }
    return $values;
  }

  /**
   * Assert that a field has the expected values in an entity.
   *
   * This function only checks a single column in the field values.
   *
   * @param EntityInterface $entity
   *   The entity to test.
   * @param $field_name
   *   The name of the field to test
   * @param $langcode
   *   The language code for the values.
   * @param $expected_values
   *   The array of expected values.
   * @param $column
   *   (Optional) the name of the column to check.
   */
  function assertFieldValues(EntityInterface $entity, $field_name, $langcode, $expected_values, $column = 'value') {
    $e = clone $entity;
    field_attach_load('entity_test', array($e->id() => $e));
    $values = isset($e->{$field_name}[$langcode]) ? $e->{$field_name}[$langcode] : array();
    $this->assertEqual(count($values), count($expected_values), 'Expected number of values were saved.');
    foreach ($expected_values as $key => $value) {
      $this->assertEqual($values[$key][$column], $value, format_string('Value @value was saved correctly.', array('@value' => $value)));
    }
  }

  /**
   * Pass if the raw text IS found in set string.
   *
   * @param $raw
   *   Raw (HTML) string to look for.
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
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertRaw($raw, $message = '', $group = 'Other') {
    if (!$message) {
      $message = t('Raw "@raw" found', array('@raw' => $raw));
    }
    return $this->assert(strpos($this->content, $raw) !== FALSE, $message, $group);
  }


  /**
   * Pass if the raw text IS NOT found in set string.
   *
   * @param $raw
   *   Raw (HTML) string to look for.
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
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoRaw($raw, $message = '', $group = 'Other') {
    if (!$message) {
      $message = t('Raw "@raw" found', array('@raw' => $raw));
    }
    return $this->assert(strpos($this->content, $raw) === FALSE, $message, $group);
  }

  /**
   * Pass if the text IS found in set string.
   *
   * @param $text
   *   Text to look for.
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
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertText($text, $message = '', $group = 'Other') {
    if (!$message) {
      $message = t('Raw "@raw" found', array('@raw' => $text));
    }
    return $this->assert(strpos(filter_xss($this->content, array()), $text) !== FALSE, $message, $group);
  }

  /**
   * Pass if the text IS NOT found in set string.
   *
   * @param $text
   *   Text to look for.
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
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoText($text, $message = '', $group = 'Other') {
    if (!$message) {
      $message = t('Raw "@raw" not found', array('@raw' => $text));
    }
    return $this->assert(strpos(filter_xss($this->content, array()), $text) === FALSE, $message, $group);
  }
}
