<?php

/**
 * @file
 * Definition of Drupal\field\Tests\FieldAttachOtherTest.
 */

namespace Drupal\field\Tests;

use Drupal\Core\Language\Language;

/**
 * Unit test class for non-storage related field_attach_* functions.
 */
class FieldAttachOtherTest extends FieldUnitTestBase {

  /**
   * Field name to use in the test.
   *
   * @var string
   */
  protected $field_name;

  /**
   * Field name to use in the test.
   *
   * @var string
   */
  protected $field_name_2;

  public static function getInfo() {
    return array(
      'name' => 'Field attach tests (other)',
      'description' => 'Test other Field Attach API functions.',
      'group' => 'Field API',
    );
  }

  public function setUp() {
    parent::setUp();
    $this->createFieldWithInstance();
  }

  /**
   * Test field_attach_view() and field_attach_prepare_view().
   */
  function testFieldAttachView() {
    $this->createFieldWithInstance('_2');

    $entity_type = 'entity_test';
    $entity_init = entity_create($entity_type, array());
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $options = array('field_name' => $this->field_name_2);

    // Populate values to be displayed.
    $values = $this->_generateTestFieldValues($this->field['cardinality']);
    $entity_init->{$this->field_name}->setValue($values);
    $values_2 = $this->_generateTestFieldValues($this->field_2['cardinality']);
    $entity_init->{$this->field_name_2}->setValue($values_2);

    // Simple formatter, label displayed.
    $entity = clone($entity_init);
    $display = entity_get_display($entity_type, $entity->bundle(), 'full');
    $displays = array($entity->bundle() => $display);

    $formatter_setting = $this->randomName();
    $display_options = array(
      'label' => 'above',
      'type' => 'field_test_default',
      'settings' => array(
        'test_formatter_setting' => $formatter_setting,
      ),
    );
    $display->setComponent($this->field['field_name'], $display_options);

    $formatter_setting_2 = $this->randomName();
    $display_options_2 = array(
      'label' => 'above',
      'type' => 'field_test_default',
      'settings' => array(
        'test_formatter_setting' => $formatter_setting_2,
      ),
    );
    $display->setComponent($this->field_2['field_name'], $display_options_2);

    // View all fields.
    field_attach_prepare_view($entity_type, array($entity->id() => $entity), $displays);
    $content = field_attach_view($entity, $display);
    $output = drupal_render($content);
    $this->content = $output;
    $this->assertRaw($this->instance['label'], "First field's label is displayed.");
    foreach ($values as $delta => $value) {
      $this->content = $output;
      $this->assertRaw("$formatter_setting|{$value['value']}", "Value $delta is displayed, formatter settings are applied.");
    }
    $this->assertRaw($this->instance_2['label'], "Second field's label is displayed.");
    foreach ($values_2 as $delta => $value) {
      $this->content = $output;
      $this->assertRaw("$formatter_setting_2|{$value['value']}", "Value $delta is displayed, formatter settings are applied.");
    }

    // Label hidden.
    $entity = clone($entity_init);
    $display_options['label'] = 'hidden';
    $display->setComponent($this->field['field_name'], $display_options);
    field_attach_prepare_view($entity_type, array($entity->id() => $entity), $displays);
    $entity->content = field_attach_view($entity, $display);
    $output = drupal_render($entity->content);
    $this->content = $output;
    $this->assertNoRaw($this->instance['label'], "Hidden label: label is not displayed.");

    // Field hidden.
    $entity = clone($entity_init);
    $display->removeComponent($this->field['field_name']);
    field_attach_prepare_view($entity_type, array($entity->id() => $entity), $displays);
    $entity->content = field_attach_view($entity, $display);
    $output = drupal_render($entity->content);
    $this->content = $output;
    $this->assertNoRaw($this->instance['label'], "Hidden field: label is not displayed.");
    foreach ($values as $delta => $value) {
      $this->assertNoRaw("$formatter_setting|{$value['value']}", "Hidden field: value $delta is not displayed.");
    }

    // Multiple formatter.
    $entity = clone($entity_init);
    $formatter_setting = $this->randomName();
    $display->setComponent($this->field['field_name'], array(
      'label' => 'above',
      'type' => 'field_test_multiple',
      'settings' => array(
        'test_formatter_setting_multiple' => $formatter_setting,
      ),
    ));
    field_attach_prepare_view($entity_type, array($entity->id() => $entity), $displays);
    $entity->content = field_attach_view($entity, $display);
    $output = drupal_render($entity->content);
    $expected_output = $formatter_setting;
    foreach ($values as $delta => $value) {
      $expected_output .= "|$delta:{$value['value']}";
    }
    $this->content = $output;
    $this->assertRaw($expected_output, "Multiple formatter: all values are displayed, formatter settings are applied.");

    // Test a formatter that uses hook_field_formatter_prepare_view().
    $entity = clone($entity_init);
    $formatter_setting = $this->randomName();
    $display->setComponent($this->field['field_name'], array(
      'label' => 'above',
      'type' => 'field_test_with_prepare_view',
      'settings' => array(
        'test_formatter_setting_additional' => $formatter_setting,
      ),
    ));
    field_attach_prepare_view($entity_type, array($entity->id() => $entity), $displays);
    $entity->content = field_attach_view($entity, $display);
    $output = drupal_render($entity->content);
    $this->content = $output;
    foreach ($values as $delta => $value) {
      $expected = $formatter_setting . '|' . $value['value'] . '|' . ($value['value'] + 1);
      $this->assertRaw($expected, "Value $delta is displayed, formatter settings are applied.");
    }

    // TODO:
    // - check display order with several fields

    // Preprocess template.
    $variables = array();
    field_attach_preprocess($entity, $entity->content, $variables);
    $result = TRUE;
    foreach ($values as $delta => $item) {
      if ($variables[$this->field_name][$delta]['value'] !== $item['value']) {
        $result = FALSE;
        break;
      }
    }
    $this->assertTrue($result, format_string('Variable $@field_name correctly populated.', array('@field_name' => $this->field_name)));
  }

  /**
   * Tests the 'multiple entity' behavior of field_attach_prepare_view().
   */
  function testFieldAttachPrepareViewMultiple() {
    $entity_type = 'entity_test';

    // Set the instance to be hidden.
    $display = entity_get_display('entity_test', 'entity_test', 'full')
      ->removeComponent($this->field['field_name']);

    // Set up a second instance on another bundle, with a formatter that uses
    // hook_field_formatter_prepare_view().
    entity_test_create_bundle('test_bundle_2');
    $formatter_setting = $this->randomName();
    $instance_definition = $this->instance_definition;
    $instance_definition['bundle'] = 'test_bundle_2';
    $this->instance2 = entity_create('field_instance', $instance_definition);
    $this->instance2->save();

    $display_2 = entity_get_display('entity_test', 'test_bundle_2', 'full')
      ->setComponent($this->field['field_name'], array(
        'type' => 'field_test_with_prepare_view',
        'settings' => array(
          'test_formatter_setting_additional' => $formatter_setting,
        ),
      ));

    $displays = array('entity_test' => $display, 'test_bundle_2' => $display_2);

    // Create one entity in each bundle.
    $entity1_init = entity_create('entity_test', array('id' => 1, 'type' => 'entity_test'));
    $values1 = $this->_generateTestFieldValues($this->field['cardinality']);
    $entity1_init->{$this->field_name}->setValue($values1);

    $entity2_init = entity_create('entity_test', array('id' => 2, 'type' => 'test_bundle_2'));
    $values2 = $this->_generateTestFieldValues($this->field['cardinality']);
    $entity2_init->{$this->field_name}->setValue($values2);

    // Run prepare_view, and check that the entities come out as expected.
    $entity1 = clone($entity1_init);
    $entity2 = clone($entity2_init);
    $entities = array($entity1->id() => $entity1, $entity2->id() => $entity2);
    field_attach_prepare_view($entity_type, $entities, $displays);
    $this->assertFalse(isset($entity1->{$this->field_name}->additional_formatter_value), 'Entity 1 did not run through the prepare_view hook.');
    $this->assertTrue(isset($entity2->{$this->field_name}->additional_formatter_value), 'Entity 2 ran through the prepare_view hook.');

    // Same thing, reversed order.
    $entity1 = clone($entity1_init);
    $entity2 = clone($entity2_init);
    $entities = array($entity1->id() => $entity1, $entity2->id() => $entity2);
    field_attach_prepare_view($entity_type, $entities, $displays);
    $this->assertFalse(isset($entity1->{$this->field_name}->additional_formatter_value), 'Entity 1 did not run through the prepare_view hook.');
    $this->assertTrue(isset($entity2->{$this->field_name}->additional_formatter_value), 'Entity 2 ran through the prepare_view hook.');
  }

  /**
   * Test field cache.
   */
  function testFieldAttachCache() {
    // Initialize random values and a test entity.
    $entity_init = entity_create('entity_test', array('id' => 1, 'revision_id' => 1, 'type' => $this->instance['bundle']));
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $values = $this->_generateTestFieldValues($this->field['cardinality']);

    // Non-cacheable entity type.
    $entity_type = 'entity_test';
    $cid = "field:$entity_type:" . $entity_init->id();

    // Check that no initial cache entry is present.
    $this->assertFalse(cache('field')->get($cid), 'Non-cached: no initial cache entry');

    // Save, and check that no cache entry is present.
    $entity = clone($entity_init);
    $entity->{$this->field_name}->setValue($values);
    field_attach_insert($entity);
    $this->assertFalse(cache('field')->get($cid), 'Non-cached: no cache entry on insert');

    // Load, and check that no cache entry is present.
    $entity = clone($entity_init);
    field_attach_load($entity_type, array($entity->id() => $entity));
    $this->assertFalse(cache('field')->get($cid), 'Non-cached: no cache entry on load');


    // Cacheable entity type.
    $entity_type = 'entity_test_cache';
    $cid = "field:$entity_type:" . $entity_init->id();
    $instance_definition = $this->instance_definition;
    $instance_definition['entity_type'] = $entity_type;
    $instance_definition['bundle'] = $entity_type;
    entity_create('field_instance', $instance_definition)->save();

    entity_info_cache_clear();

    $entity_init = entity_create($entity_type, array(
      'id' => 1,
      'revision_id' => 1,
      'type' => $entity_type,
    ));

    // Check that no initial cache entry is present.
    $this->assertFalse(cache('field')->get($cid), 'Cached: no initial cache entry');

    // Save, and check that no cache entry is present.
    $entity = clone($entity_init);
    $entity->{$this->field_name} = $values;
    field_attach_insert($entity);
    $this->assertFalse(cache('field')->get($cid), 'Cached: no cache entry on insert');

    // Load a single field, and check that no cache entry is present.
    $entity = clone($entity_init);
    $instance = field_info_instance($entity->entityType(), $this->field_name, $entity->bundle());
    field_attach_load($entity_type, array($entity->id() => $entity), FIELD_LOAD_CURRENT, array('instance' => $instance));
    $cache = cache('field')->get($cid);
    $this->assertFalse($cache, 'Cached: no cache entry on loading a single field');

    // Load, and check that a cache entry is present with the expected values.
    $entity = clone($entity_init);
    field_attach_load($entity_type, array($entity->id() => $entity));
    $cache = cache('field')->get($cid);
    $this->assertEqual($cache->data[$this->field_name][$langcode], $values, 'Cached: correct cache entry on load');

    // Update with different values, and check that the cache entry is wiped.
    $values = $this->_generateTestFieldValues($this->field['cardinality']);
    $entity = clone($entity_init);
    $entity->{$this->field_name} = $values;
    field_attach_update($entity);
    $this->assertFalse(cache('field')->get($cid), 'Cached: no cache entry on update');

    // Load, and check that a cache entry is present with the expected values.
    $entity = clone($entity_init);
    field_attach_load($entity_type, array($entity->id() => $entity));
    $cache = cache('field')->get($cid);
    $this->assertEqual($cache->data[$this->field_name][$langcode], $values, 'Cached: correct cache entry on load');

    // Create a new revision, and check that the cache entry is wiped.
    $entity_init = entity_create($entity_type, array(
      'id' => 1,
      'revision_id' => 2,
      'type' => $entity_type,
    ));
    $values = $this->_generateTestFieldValues($this->field['cardinality']);
    $entity = clone($entity_init);
    $entity->{$this->field_name} = $values;
    field_attach_update($entity);
    $cache = cache('field')->get($cid);
    $this->assertFalse(cache('field')->get($cid), 'Cached: no cache entry on new revision creation');

    // Load, and check that a cache entry is present with the expected values.
    $entity = clone($entity_init);
    field_attach_load($entity_type, array($entity->id() => $entity));
    $cache = cache('field')->get($cid);
    $this->assertEqual($cache->data[$this->field_name][$langcode], $values, 'Cached: correct cache entry on load');

    // Delete, and check that the cache entry is wiped.
    field_attach_delete($entity);
    $this->assertFalse(cache('field')->get($cid), 'Cached: no cache entry after delete');
  }

  /**
   * Test field_attach_form().
   *
   * This could be much more thorough, but it does verify that the correct
   * widgets show up.
   */
  function testFieldAttachForm() {
    $this->createFieldWithInstance('_2');

    $entity_type = 'entity_test';
    $entity = entity_create($entity_type, array('id' => 1, 'revision_id' => 1, 'type' => $this->instance['bundle']));
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // When generating form for all fields.
    $form = array();
    $form_state = form_state_defaults();
    $form_state['form_display'] = entity_get_form_display($entity_type, $this->instance['bundle'], 'default');
    field_attach_form($entity, $form, $form_state);

    $this->assertEqual($form[$this->field_name][$langcode]['#title'], $this->instance['label'], "First field's form title is {$this->instance['label']}");
    $this->assertEqual($form[$this->field_name_2][$langcode]['#title'], $this->instance_2['label'], "Second field's form title is {$this->instance_2['label']}");
    for ($delta = 0; $delta < $this->field['cardinality']; $delta++) {
      // field_test_widget uses 'textfield'
      $this->assertEqual($form[$this->field_name][$langcode][$delta]['value']['#type'], 'textfield', "First field's form delta $delta widget is textfield");
    }
    for ($delta = 0; $delta < $this->field_2['cardinality']; $delta++) {
      // field_test_widget uses 'textfield'
      $this->assertEqual($form[$this->field_name_2][$langcode][$delta]['value']['#type'], 'textfield', "Second field's form delta $delta widget is textfield");
    }

    // When generating form for a single field (the second field).
    $options = array('field_name' => $this->field_name_2);
    $form = array();
    $form_state = form_state_defaults();
    $form_state['form_display'] = entity_get_form_display($entity_type, $this->instance['bundle'], 'default');
    field_attach_form($entity, $form, $form_state, NULL, $options);

    $this->assertFalse(isset($form[$this->field_name]), 'The first field does not exist in the form');
    $this->assertEqual($form[$this->field_name_2][$langcode]['#title'], $this->instance_2['label'], "Second field's form title is {$this->instance_2['label']}");
    for ($delta = 0; $delta < $this->field_2['cardinality']; $delta++) {
      // field_test_widget uses 'textfield'
      $this->assertEqual($form[$this->field_name_2][$langcode][$delta]['value']['#type'], 'textfield', "Second field's form delta $delta widget is textfield");
    }
  }

  /**
   * Test field_attach_extract_form_values().
   */
  function testFieldAttachExtractFormValues() {
    $this->createFieldWithInstance('_2');

    $entity_type = 'entity_test';
    $entity_init = entity_create($entity_type, array('id' => 1, 'revision_id' => 1, 'type' => $this->instance['bundle']));
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Build the form for all fields.
    $form = array();
    $form_state = form_state_defaults();
    $form_state['form_display'] = entity_get_form_display($entity_type, $this->instance['bundle'], 'default');
    field_attach_form($entity_init, $form, $form_state);

    // Simulate incoming values.
    // First field.
    $values = array();
    $weights = array();
    for ($delta = 0; $delta < $this->field['cardinality']; $delta++) {
      $values[$delta]['value'] = mt_rand(1, 127);
      // Assign random weight.
      do {
        $weight = mt_rand(0, $this->field['cardinality']);
      } while (in_array($weight, $weights));
      $weights[$delta] = $weight;
      $values[$delta]['_weight'] = $weight;
    }
    // Leave an empty value. 'field_test' fields are empty if empty().
    $values[1]['value'] = 0;
    // Second field.
    $values_2 = array();
    $weights_2 = array();
    for ($delta = 0; $delta < $this->field_2['cardinality']; $delta++) {
      $values_2[$delta]['value'] = mt_rand(1, 127);
      // Assign random weight.
      do {
        $weight = mt_rand(0, $this->field_2['cardinality']);
      } while (in_array($weight, $weights_2));
      $weights_2[$delta] = $weight;
      $values_2[$delta]['_weight'] = $weight;
    }
    // Leave an empty value. 'field_test' fields are empty if empty().
    $values_2[1]['value'] = 0;

    // Pretend the form has been built.
    drupal_prepare_form('field_test_entity_form', $form, $form_state);
    drupal_process_form('field_test_entity_form', $form, $form_state);
    $form_state['values'][$this->field_name][$langcode] = $values;
    $form_state['values'][$this->field_name_2][$langcode] = $values_2;

    // Call field_attach_extract_form_values() for all fields.
    $entity = clone($entity_init);
    field_attach_extract_form_values($entity, $form, $form_state);

    asort($weights);
    asort($weights_2);
    $expected_values = array();
    $expected_values_2 = array();
    foreach ($weights as $key => $value) {
      if ($key != 1) {
        $expected_values[] = array('value' => $values[$key]['value']);
      }
    }
    $this->assertIdentical($entity->{$this->field_name}->getValue(), $expected_values, 'Submit filters empty values');
    foreach ($weights_2 as $key => $value) {
      if ($key != 1) {
        $expected_values_2[] = array('value' => $values_2[$key]['value']);
      }
    }
    $this->assertIdentical($entity->{$this->field_name_2}->getValue(), $expected_values_2, 'Submit filters empty values');

    // Call field_attach_extract_form_values() for a single field (the second field).
    $options = array('field_name' => $this->field_name_2);
    $entity = clone($entity_init);
    field_attach_extract_form_values($entity, $form, $form_state, $options);
    $expected_values_2 = array();
    foreach ($weights_2 as $key => $value) {
      if ($key != 1) {
        $expected_values_2[] = array('value' => $values_2[$key]['value']);
      }
    }
    $this->assertTrue($entity->{$this->field_name}->isEmpty(), 'The first field does is empty in the entity object');
    $this->assertIdentical($entity->{$this->field_name_2}->getValue(), $expected_values_2, 'Submit filters empty values');
  }

}
