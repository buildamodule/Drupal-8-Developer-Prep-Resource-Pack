<?php

/**
 * @file
 * Definition of Drupal\options\Tests\OptionsFieldTest.
 */

namespace Drupal\options\Tests;

use Drupal\Core\Language\Language;
use Drupal\field\FieldException;

/**
 * Tests for the 'Options' field types.
 */
class OptionsFieldTest extends OptionsFieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('options');

  public static function getInfo() {
    return array(
      'name' => 'Options field',
      'description' => 'Test the Options field type.',
      'group' => 'Field types',
    );
  }

  /**
   * Test that allowed values can be updated.
   */
  function testUpdateAllowedValues() {
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // All three options appear.
    $entity = entity_create('entity_test', array());
    $form = \Drupal::entityManager()->getForm($entity);
    $this->assertTrue(!empty($form[$this->fieldName][$langcode][1]), 'Option 1 exists');
    $this->assertTrue(!empty($form[$this->fieldName][$langcode][2]), 'Option 2 exists');
    $this->assertTrue(!empty($form[$this->fieldName][$langcode][3]), 'Option 3 exists');

    // Use one of the values in an actual entity, and check that this value
    // cannot be removed from the list.
    $entity = entity_create('entity_test', array());
    $entity->{$this->fieldName}->value = 1;
    $entity->save();
    $this->field->settings['allowed_values'] = array(2 => 'Two');
    try {
      $this->field->save();
      $this->fail(t('Cannot update a list field to not include keys with existing data.'));
    }
    catch (FieldException $e) {
      $this->pass(t('Cannot update a list field to not include keys with existing data.'));
    }
    // Empty the value, so that we can actually remove the option.
    unset($entity->{$this->fieldName});
    $entity->save();

    // Removed options do not appear.
    $this->field->settings['allowed_values'] = array(2 => 'Two');
    $this->field->save();
    $entity = entity_create('entity_test', array());
    $form = \Drupal::entityManager()->getForm($entity);
    $this->assertTrue(empty($form[$this->fieldName][$langcode][1]), 'Option 1 does not exist');
    $this->assertTrue(!empty($form[$this->fieldName][$langcode][2]), 'Option 2 exists');
    $this->assertTrue(empty($form[$this->fieldName][$langcode][3]), 'Option 3 does not exist');

    // Completely new options appear.
    $this->field['settings']['allowed_values'] = array(10 => 'Update', 20 => 'Twenty');
    $this->field->save();
    $form = \Drupal::entityManager()->getForm($entity);
    $this->assertTrue(empty($form[$this->fieldName][$langcode][1]), 'Option 1 does not exist');
    $this->assertTrue(empty($form[$this->fieldName][$langcode][2]), 'Option 2 does not exist');
    $this->assertTrue(empty($form[$this->fieldName][$langcode][3]), 'Option 3 does not exist');
    $this->assertTrue(!empty($form[$this->fieldName][$langcode][10]), 'Option 10 exists');
    $this->assertTrue(!empty($form[$this->fieldName][$langcode][20]), 'Option 20 exists');

    // Options are reset when a new field with the same name is created.
    $this->field->delete();
    entity_create('field_entity', $this->fieldDefinition)->save();
    entity_create('field_instance', array(
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ))->save();
    entity_get_form_display('entity_test', 'entity_test', 'default')
      ->setComponent($this->fieldName, array(
        'type' => 'options_buttons',
      ))
      ->save();
    $entity = entity_create('entity_test', array());
    $form = \Drupal::entityManager()->getForm($entity);
    $this->assertTrue(!empty($form[$this->fieldName][$langcode][1]), 'Option 1 exists');
    $this->assertTrue(!empty($form[$this->fieldName][$langcode][2]), 'Option 2 exists');
    $this->assertTrue(!empty($form[$this->fieldName][$langcode][3]), 'Option 3 exists');
  }
}
