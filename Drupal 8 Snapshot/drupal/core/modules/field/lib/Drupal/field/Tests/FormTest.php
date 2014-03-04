<?php

/**
 * @file
 * Definition of Drupal\field\Tests\FormTest.
 */

namespace Drupal\field\Tests;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;

class FormTest extends FieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'field_test', 'options', 'entity_test');

  /**
   * An array of values defining a field single.
   *
   * @var array
   */
  protected $field_single;

  /**
   * An array of values defining a field multiple.
   *
   * @var array
   */
  protected $field_multiple;

  /**
   * An array of values defining a field with unlimited cardinality.
   *
   * @var array
   */
  protected $field_unlimited;

  /**
   * An array of values defining a field instance.
   *
   * @var array
   */
  protected $instance;

  public static function getInfo() {
    return array(
      'name' => 'Field form tests',
      'description' => 'Test Field form handling.',
      'group' => 'Field API',
    );
  }

  function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser(array('view test entity', 'administer entity_test content'));
    $this->drupalLogin($web_user);

    $this->field_single = array('field_name' => 'field_single', 'type' => 'test_field');
    $this->field_multiple = array('field_name' => 'field_multiple', 'type' => 'test_field', 'cardinality' => 4);
    $this->field_unlimited = array('field_name' => 'field_unlimited', 'type' => 'test_field', 'cardinality' => FIELD_CARDINALITY_UNLIMITED);

    $this->instance = array(
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => $this->randomName() . '_label',
      'description' => '[site:name]_description',
      'weight' => mt_rand(0, 127),
      'settings' => array(
        'test_instance_setting' => $this->randomName(),
      ),
    );
  }

  function testFieldFormSingle() {
    $field = $this->field_single;
    $field_name = $field['field_name'];
    $this->instance['field_name'] = $field_name;
    entity_create('field_entity', $field)->save();
    entity_create('field_instance', $this->instance)->save();
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($field_name)
      ->save();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Display creation form.
    $this->drupalGet('entity_test/add');

    // Create token value expected for description.
    $token_description = check_plain(\Drupal::config('system.site')->get('name')) . '_description';
    $this->assertText($token_description, 'Token replacement for description is displayed');
    $this->assertFieldByName("{$field_name}[$langcode][0][value]", '', 'Widget is displayed');
    $this->assertNoField("{$field_name}[$langcode][1][value]", 'No extraneous widget is displayed');

    // Check that hook_field_widget_form_alter() does not believe this is the
    // default value form.
    $this->assertNoText('From hook_field_widget_form_alter(): Default form is true.', 'Not default value form in hook_field_widget_form_alter().');

    // Submit with invalid value (field-level validation).
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode][0][value]" => -1
    );
    $this->drupalPost(NULL, $edit, t('Save'));
    $this->assertRaw(t('%name does not accept the value -1.', array('%name' => $this->instance['label'])), 'Field validation fails with invalid input.');
    // TODO : check that the correct field is flagged for error.

    // Create an entity
    $value = mt_rand(1, 127);
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode][0][value]" => $value,
    );
    $this->drupalPost(NULL, $edit, t('Save'));
    preg_match('|entity_test/manage/(\d+)/edit|', $this->url, $match);
    $id = $match[1];
    $this->assertText(t('entity_test @id has been created.', array('@id' => $id)), 'Entity was created');
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->{$field_name}->value, $value, 'Field value was saved');

    // Display edit form.
    $this->drupalGet('entity_test/manage/' . $id . '/edit');
    $this->assertFieldByName("{$field_name}[$langcode][0][value]", $value, 'Widget is displayed with the correct default value');
    $this->assertNoField("{$field_name}[$langcode][1][value]", 'No extraneous widget is displayed');

    // Update the entity.
    $value = mt_rand(1, 127);
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode][0][value]" => $value,
    );
    $this->drupalPost(NULL, $edit, t('Save'));
    $this->assertText(t('entity_test @id has been updated.', array('@id' => $id)), 'Entity was updated');
    $this->container->get('plugin.manager.entity')->getStorageController('entity_test')->resetCache(array($id));
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->{$field_name}->value, $value, 'Field value was updated');

    // Empty the field.
    $value = '';
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode][0][value]" => $value
    );
    $this->drupalPost('entity_test/manage/' . $id . '/edit', $edit, t('Save'));
    $this->assertText(t('entity_test @id has been updated.', array('@id' => $id)), 'Entity was updated');
    $this->container->get('plugin.manager.entity')->getStorageController('entity_test')->resetCache(array($id));
    $entity = entity_load('entity_test', $id);
    $this->assertTrue($entity->{$field_name}->isEmpty(), 'Field was emptied');
  }

  /**
   * Tests field widget default values on entity forms.
   */
  function testFieldFormDefaultValue() {
    $field = $this->field_single;
    $field_name = $field['field_name'];
    $this->instance['field_name'] = $field_name;
    $default = rand(1, 127);
    $this->instance['default_value'] = array(array('value' => $default));
    entity_create('field_entity', $field)->save();
    entity_create('field_instance', $this->instance)->save();
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($field_name)
      ->save();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Display creation form.
    $this->drupalGet('entity_test/add');
    // Test that the default value is displayed correctly.
    $this->assertFieldByXpath("//input[@name='{$field_name}[$langcode][0][value]' and @value='$default']");

    // Try to submit an empty value.
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode][0][value]" => '',
    );
    $this->drupalPost(NULL, $edit, t('Save'));
    preg_match('|entity_test/manage/(\d+)/edit|', $this->url, $match);
    $id = $match[1];
    $this->assertText(t('entity_test @id has been created.', array('@id' => $id)), 'Entity was created.');
    $entity = entity_load('entity_test', $id);
    $this->assertTrue($entity->{$field_name}->isEmpty(), 'Field is now empty.');
  }

  function testFieldFormSingleRequired() {
    $field = $this->field_single;
    $field_name = $field['field_name'];
    $this->instance['field_name'] = $field_name;
    $this->instance['required'] = TRUE;
    entity_create('field_entity', $field)->save();
    entity_create('field_instance', $this->instance)->save();
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($field_name)
      ->save();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Submit with missing required value.
    $edit = array();
    $this->drupalPost('entity_test/add', $edit, t('Save'));
    $this->assertRaw(t('!name field is required.', array('!name' => $this->instance['label'])), 'Required field with no value fails validation');

    // Create an entity
    $value = mt_rand(1, 127);
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode][0][value]" => $value,
    );
    $this->drupalPost(NULL, $edit, t('Save'));
    preg_match('|entity_test/manage/(\d+)/edit|', $this->url, $match);
    $id = $match[1];
    $this->assertText(t('entity_test @id has been created.', array('@id' => $id)), 'Entity was created');
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->{$field_name}->value, $value, 'Field value was saved');

    // Edit with missing required value.
    $value = '';
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode][0][value]" => $value,
    );
    $this->drupalPost('entity_test/manage/' . $id . '/edit', $edit, t('Save'));
    $this->assertRaw(t('!name field is required.', array('!name' => $this->instance['label'])), 'Required field with no value fails validation');
  }

//  function testFieldFormMultiple() {
//    $this->field = $this->field_multiple;
//    $field_name = $this->field['field_name'];
//    $this->instance['field_name'] = $field_name;
//    entity_create('field_entity', $this->field)->save();
//    entity_create('field_instance', $this->instance)->save();
//  }

  function testFieldFormUnlimited() {
    $field = $this->field_unlimited;
    $field_name = $field['field_name'];
    $this->instance['field_name'] = $field_name;
    entity_create('field_entity', $field)->save();
    entity_create('field_instance', $this->instance)->save();
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($field_name)
      ->save();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Display creation form -> 1 widget.
    $this->drupalGet('entity_test/add');
    $this->assertFieldByName("{$field_name}[$langcode][0][value]", '', 'Widget 1 is displayed');
    $this->assertNoField("{$field_name}[$langcode][1][value]", 'No extraneous widget is displayed');

    // Press 'add more' button -> 2 widgets.
    $this->drupalPost(NULL, array(), t('Add another item'));
    $this->assertFieldByName("{$field_name}[$langcode][0][value]", '', 'Widget 1 is displayed');
    $this->assertFieldByName("{$field_name}[$langcode][1][value]", '', 'New widget is displayed');
    $this->assertNoField("{$field_name}[$langcode][2][value]", 'No extraneous widget is displayed');
    // TODO : check that non-field inpurs are preserved ('title')...

    // Yet another time so that we can play with more values -> 3 widgets.
    $this->drupalPost(NULL, array(), t('Add another item'));

    // Prepare values and weights.
    $count = 3;
    $delta_range = $count - 1;
    $values = $weights = $pattern = $expected_values = array();
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
    );
    for ($delta = 0; $delta <= $delta_range; $delta++) {
      // Assign unique random values and weights.
      do {
        $value = mt_rand(1, 127);
      } while (in_array($value, $values));
      do {
        $weight = mt_rand(-$delta_range, $delta_range);
      } while (in_array($weight, $weights));
      $edit["{$field_name}[$langcode][$delta][value]"] = $value;
      $edit["{$field_name}[$langcode][$delta][_weight]"] = $weight;
      // We'll need three slightly different formats to check the values.
      $values[$delta] = $value;
      $weights[$delta] = $weight;
      $field_values[$weight]['value'] = (string) $value;
      $pattern[$weight] = "<input [^>]*value=\"$value\" [^>]*";
    }

    // Press 'add more' button -> 4 widgets
    $this->drupalPost(NULL, $edit, t('Add another item'));
    for ($delta = 0; $delta <= $delta_range; $delta++) {
      $this->assertFieldByName("{$field_name}[$langcode][$delta][value]", $values[$delta], "Widget $delta is displayed and has the right value");
      $this->assertFieldByName("{$field_name}[$langcode][$delta][_weight]", $weights[$delta], "Widget $delta has the right weight");
    }
    ksort($pattern);
    $pattern = implode('.*', array_values($pattern));
    $this->assertPattern("|$pattern|s", 'Widgets are displayed in the correct order');
    $this->assertFieldByName("{$field_name}[$langcode][$delta][value]", '', "New widget is displayed");
    $this->assertFieldByName("{$field_name}[$langcode][$delta][_weight]", $delta, "New widget has the right weight");
    $this->assertNoField("{$field_name}[$langcode][" . ($delta + 1) . '][value]', 'No extraneous widget is displayed');

    // Submit the form and create the entity.
    $this->drupalPost(NULL, $edit, t('Save'));
    preg_match('|entity_test/manage/(\d+)/edit|', $this->url, $match);
    $id = $match[1];
    $this->assertText(t('entity_test @id has been created.', array('@id' => $id)), 'Entity was created');
    $entity = entity_load('entity_test', $id);
    ksort($field_values);
    $field_values = array_values($field_values);
    $this->assertIdentical($entity->{$field_name}->getValue(), $field_values, 'Field values were saved in the correct order');

    // Display edit form: check that the expected number of widgets is
    // displayed, with correct values change values, reorder, leave an empty
    // value in the middle.
    // Submit: check that the entity is updated with correct values
    // Re-submit: check that the field can be emptied.

    // Test with several multiple fields in a form
  }

  /**
   * Tests widget handling of multiple required radios.
   */
  function testFieldFormMultivalueWithRequiredRadio() {
    // Create a multivalue test field.
    $field = $this->field_unlimited;
    $field_name = $field['field_name'];
    $this->instance['field_name'] = $field_name;
    entity_create('field_entity', $field)->save();
    entity_create('field_instance', $this->instance)->save();
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($field_name)
      ->save();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Add a required radio field.
    entity_create('field_entity', array(
      'field_name' => 'required_radio_test',
      'type' => 'list_text',
      'settings' => array(
        'allowed_values' => array('yes' => 'yes', 'no' => 'no'),
      ),
    ))->save();
    $instance = array(
      'field_name' => 'required_radio_test',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'required' => TRUE,
    );
    entity_create('field_instance', $instance)->save();
    entity_get_form_display($instance['entity_type'], $instance['bundle'], 'default')
      ->setComponent($instance['field_name'], array(
        'type' => 'options_buttons',
      ))
      ->save();

    // Display creation form.
    $this->drupalGet('entity_test/add');

    // Press the 'Add more' button.
    $this->drupalPost(NULL, array(), t('Add another item'));

    // Verify that no error is thrown by the radio element.
    $this->assertNoFieldByXpath('//div[contains(@class, "error")]', FALSE, 'No error message is displayed.');

    // Verify that the widget is added.
    $this->assertFieldByName("{$field_name}[$langcode][0][value]", '', 'Widget 1 is displayed');
    $this->assertFieldByName("{$field_name}[$langcode][1][value]", '', 'New widget is displayed');
    $this->assertNoField("{$field_name}[$langcode][2][value]", 'No extraneous widget is displayed');
  }

  function testFieldFormJSAddMore() {
    $field = $this->field_unlimited;
    $field_name = $field['field_name'];
    $this->instance['field_name'] = $field_name;
    entity_create('field_entity', $field)->save();
    entity_create('field_instance', $this->instance)->save();
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($field_name)
      ->save();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Display creation form -> 1 widget.
    $this->drupalGet('entity_test/add');

    // Press 'add more' button a couple times -> 3 widgets.
    // drupalPostAJAX() will not work iteratively, so we add those through
    // non-JS submission.
    $this->drupalPost(NULL, array(), t('Add another item'));
    $this->drupalPost(NULL, array(), t('Add another item'));

    // Prepare values and weights.
    $count = 3;
    $delta_range = $count - 1;
    $values = $weights = $pattern = $expected_values = $edit = array();
    for ($delta = 0; $delta <= $delta_range; $delta++) {
      // Assign unique random values and weights.
      do {
        $value = mt_rand(1, 127);
      } while (in_array($value, $values));
      do {
        $weight = mt_rand(-$delta_range, $delta_range);
      } while (in_array($weight, $weights));
      $edit["{$field_name}[$langcode][$delta][value]"] = $value;
      $edit["{$field_name}[$langcode][$delta][_weight]"] = $weight;
      // We'll need three slightly different formats to check the values.
      $values[$delta] = $value;
      $weights[$delta] = $weight;
      $field_values[$weight]['value'] = (string) $value;
      $pattern[$weight] = "<input [^>]*value=\"$value\" [^>]*";
    }
    // Press 'add more' button through Ajax, and place the expected HTML result
    // as the tested content.
    $commands = $this->drupalPostAJAX(NULL, $edit, $field_name . '_add_more');
    $this->content = $commands[1]['data'];

    for ($delta = 0; $delta <= $delta_range; $delta++) {
      $this->assertFieldByName("{$field_name}[$langcode][$delta][value]", $values[$delta], "Widget $delta is displayed and has the right value");
      $this->assertFieldByName("{$field_name}[$langcode][$delta][_weight]", $weights[$delta], "Widget $delta has the right weight");
    }
    ksort($pattern);
    $pattern = implode('.*', array_values($pattern));
    $this->assertPattern("|$pattern|s", 'Widgets are displayed in the correct order');
    $this->assertFieldByName("{$field_name}[$langcode][$delta][value]", '', "New widget is displayed");
    $this->assertFieldByName("{$field_name}[$langcode][$delta][_weight]", $delta, "New widget has the right weight");
    $this->assertNoField("{$field_name}[$langcode][" . ($delta + 1) . '][value]', 'No extraneous widget is displayed');
  }

  /**
   * Tests widgets handling multiple values.
   */
  function testFieldFormMultipleWidget() {
    // Create a field with fixed cardinality and an instance using a multiple
    // widget.
    $field = $this->field_multiple;
    $field_name = $field['field_name'];
    $this->instance['field_name'] = $field_name;
    entity_create('field_entity', $field)->save();
    entity_create('field_instance', $this->instance)->save();
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($field_name, array(
        'type' => 'test_field_widget_multiple',
      ))
      ->save();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Display creation form.
    $this->drupalGet('entity_test/add');
    $this->assertFieldByName("{$field_name}[$langcode]", '', 'Widget is displayed.');

    // Create entity with three values.
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode]" => '1, 2, 3',
    );
    $this->drupalPost(NULL, $edit, t('Save'));
    preg_match('|entity_test/manage/(\d+)/edit|', $this->url, $match);
    $id = $match[1];

    // Check that the values were saved.
    $entity_init = entity_load('entity_test', $id);
    $this->assertFieldValues($entity_init, $field_name, $langcode, array(1, 2, 3));

    // Display the form, check that the values are correctly filled in.
    $this->drupalGet('entity_test/manage/' . $id . '/edit');
    $this->assertFieldByName("{$field_name}[$langcode]", '1, 2, 3', 'Widget is displayed.');

    // Submit the form with more values than the field accepts.
    $edit = array("{$field_name}[$langcode]" => '1, 2, 3, 4, 5');
    $this->drupalPost(NULL, $edit, t('Save'));
    $this->assertRaw('this field cannot hold more than 4 values', 'Form validation failed.');
    // Check that the field values were not submitted.
    $this->assertFieldValues($entity_init, $field_name, $langcode, array(1, 2, 3));
  }

  /**
   * Tests fields with no 'edit' access.
   */
  function testFieldFormAccess() {
    // Create a "regular" field.
    $field = $this->field_single;
    $field_name = $field['field_name'];
    $entity_type = 'entity_test_rev';
    $instance = $this->instance;
    $instance['field_name'] = $field_name;
    $instance['entity_type'] = $entity_type;
    $instance['bundle'] = $entity_type;
    entity_create('field_entity', $field)->save();
    entity_create('field_instance', $instance)->save();
    entity_get_form_display($entity_type, $entity_type, 'default')
      ->setComponent($field_name)
      ->save();

    // Create a field with no edit access - see field_test_field_access().
    $field_no_access = array(
      'field_name' => 'field_no_edit_access',
      'type' => 'test_field',
    );
    $field_name_no_access = $field_no_access['field_name'];
    $instance_no_access = array(
      'field_name' => $field_name_no_access,
      'entity_type' => $entity_type,
      'bundle' => $entity_type,
      'default_value' => array(0 => array('value' => 99)),
    );
    entity_create('field_entity', $field_no_access)->save();
    entity_create('field_instance', $instance_no_access)->save();
    entity_get_form_display($instance_no_access['entity_type'], $instance_no_access['bundle'], 'default')
      ->setComponent($field_name_no_access)
      ->save();

    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Test that the form structure includes full information for each delta
    // apart from #access.
    $entity = entity_create($entity_type, array('id' => 0, 'revision_id' => 0));

    $form = array();
    $form_state = form_state_defaults();
    $form_state['form_display'] = entity_get_form_display($entity_type, $entity_type, 'default');
    field_attach_form($entity, $form, $form_state);

    $this->assertEqual($form[$field_name_no_access][$langcode][0]['value']['#entity_type'], $entity_type, 'The correct entity type is set in the field structure.');
    $this->assertFalse($form[$field_name_no_access]['#access'], 'Field #access is FALSE for the field without edit access.');

    // Display creation form.
    $this->drupalGet($entity_type . '/add');
    $this->assertNoFieldByName("{$field_name_no_access}[$langcode][0][value]", '', 'Widget is not displayed if field access is denied.');

    // Create entity.
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode][0][value]" => 1,
    );
    $this->drupalPost(NULL, $edit, t('Save'));
    preg_match("|$entity_type/manage/(\d+)/edit|", $this->url, $match);
    $id = $match[1];

    // Check that the default value was saved.
    $entity = entity_load($entity_type, $id);
    $this->assertEqual($entity->$field_name_no_access->value, 99, 'Default value was saved for the field with no edit access.');
    $this->assertEqual($entity->$field_name->value, 1, 'Entered value vas saved for the field with edit access.');

    // Create a new revision.
    $edit = array(
      'user_id' => 1,
      'name' => $this->randomName(),
      "{$field_name}[$langcode][0][value]" => 2,
      'revision' => TRUE,
    );
    $this->drupalPost($entity_type . '/manage/' . $id . '/edit', $edit, t('Save'));

    // Check that the new revision has the expected values.
    $this->container->get('plugin.manager.entity')->getStorageController($entity_type)->resetCache(array($id));
    $entity = entity_load($entity_type, $id);
    $this->assertEqual($entity->$field_name_no_access->value, 99, 'New revision has the expected value for the field with no edit access.');
    $this->assertEqual($entity->$field_name->value, 2, 'New revision has the expected value for the field with edit access.');

    // Check that the revision is also saved in the revisions table.
//    $entity = entity_revision_load($entity_type, $entity->getRevisionId());
    $this->assertEqual($entity->$field_name_no_access->value, 99, 'New revision has the expected value for the field with no edit access.');
    $this->assertEqual($entity->$field_name->value, 2, 'New revision has the expected value for the field with edit access.');
  }

  /**
   * Tests the Hidden widget.
   */
  function testFieldFormHiddenWidget() {
    $entity_type = 'entity_test_rev';
    $field = $this->field_single;
    $field_name = $field['field_name'];
    $this->instance['field_name'] = $field_name;
    $this->instance['default_value'] = array(0 => array('value' => 99));
    $this->instance['entity_type'] = $entity_type;
    $this->instance['bundle'] = $entity_type;
    entity_create('field_entity', $field)->save();
    $this->instance = entity_create('field_instance', $this->instance);
    $this->instance->save();
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($this->instance['field_name'], array(
        'type' => 'hidden',
      ))
      ->save();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    // Display the entity creation form.
    $this->drupalGet($entity_type . '/add');

    // Create an entity and test that the default value is assigned correctly to
    // the field that uses the hidden widget.
    $this->assertNoField("{$field_name}[$langcode][0][value]", 'The hidden widget is not displayed');
    $this->drupalPost(NULL, array('user_id' => 1, 'name' => $this->randomName()), t('Save'));
    preg_match('|' . $entity_type . '/manage/(\d+)/edit|', $this->url, $match);
    $id = $match[1];
    $this->assertText(t('entity_test_rev @id has been created.', array('@id' => $id)), 'Entity was created');
    $entity = entity_load($entity_type, $id);
    $this->assertEqual($entity->{$field_name}->value, 99, 'Default value was saved');

    // Update the instance to remove the default value and switch to the
    // default widget.
    $this->instance['default_value'] = NULL;
    $this->instance->save();
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($this->instance['field_name'], array(
        'type' => 'test_field_widget',
      ))
      ->save();

    // Display edit form.
    $this->drupalGet($entity_type . '/manage/' . $id . '/edit');
    $this->assertFieldByName("{$field_name}[$langcode][0][value]", 99, 'Widget is displayed with the correct default value');

    // Update the entity.
    $value = mt_rand(1, 127);
    $edit = array("{$field_name}[$langcode][0][value]" => $value);
    $this->drupalPost(NULL, $edit, t('Save'));
    $this->assertText(t('entity_test_rev @id has been updated.', array('@id' => $id)), 'Entity was updated');
    entity_get_controller($entity_type)->resetCache(array($id));
    $entity = entity_load($entity_type, $id);
    $this->assertEqual($entity->{$field_name}->value, $value, 'Field value was updated');

    // Update the form display and switch to the Hidden widget again.
    entity_get_form_display($this->instance['entity_type'], $this->instance['bundle'], 'default')
      ->setComponent($this->instance['field_name'], array(
        'type' => 'hidden',
      ))
      ->save();

    // Create a new revision.
    $edit = array('revision' => TRUE);
    $this->drupalPost($entity_type . '/manage/' . $id . '/edit', $edit, t('Save'));

    // Check that the expected value has been carried over to the new revision.
    entity_get_controller($entity_type)->resetCache(array($id));
    $entity = entity_load($entity_type, $id);
    $this->assertEqual($entity->{$field_name}->value, $value, 'New revision has the expected value for the field with the Hidden widget');
  }

}
