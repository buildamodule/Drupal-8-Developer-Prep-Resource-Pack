<?php

/**
 * @file
 * Contains \Drupal\field_ui\Tests\ManageFieldsTest.
 */

namespace Drupal\field_ui\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests the functionality of the 'Manage fields' screen.
 */
class ManageFieldsTest extends FieldUiTestBase {
  public static function getInfo() {
    return array(
      'name' => 'Manage fields',
      'description' => 'Test the Field UI "Manage fields" screen.',
      'group' => 'Field UI',
    );
  }

  function setUp() {
    parent::setUp();

    // Create random field name.
    $this->field_label = $this->randomName(8);
    $this->field_name_input =  strtolower($this->randomName(8));
    $this->field_name = 'field_'. $this->field_name_input;

    // Create Basic page and Article node types.
    $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
    $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));

    // Create a vocabulary named "Tags".
    $vocabulary = entity_create('taxonomy_vocabulary', array(
      'name' => 'Tags',
      'vid' => 'tags',
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
    ));
    $vocabulary->save();

    $field = array(
      'field_name' => 'field_' . $vocabulary->id(),
      'type' => 'taxonomy_term_reference',
    );
    entity_create('field_entity', $field)->save();

    $instance = array(
      'field_name' => 'field_' . $vocabulary->id(),
      'entity_type' => 'node',
      'label' => 'Tags',
      'bundle' => 'article',
    );
    entity_create('field_instance', $instance)->save();

    entity_get_form_display('node', 'article', 'default')
      ->setComponent('field_' . $vocabulary->id())
      ->save();
  }

  /**
   * Runs the field CRUD tests.
   *
   * In order to act on the same fields, and not create the fields over and over
   * again the following tests create, update and delete the same fields.
   */
  function testCRUDFields() {
    $this->manageFieldsPage();
    $this->createField();
    $this->updateField();
    $this->addExistingField();
    $this->cardinalitySettings();
    $this->fieldListAdminPage();
  }

  /**
   * Tests the manage fields page.
   *
   * @param string $type
   *   (optional) The name of a content type.
   */
  function manageFieldsPage($type = '') {
    $type = empty($type) ? $this->type : $type;
    $this->drupalGet('admin/structure/types/manage/' . $type . '/fields');
    // Check all table columns.
    $table_headers = array(
      t('Label'),
      t('Machine name'),
      t('Field type'),
      t('Operations'),
    );
    foreach ($table_headers as $table_header) {
      // We check that the label appear in the table headings.
      $this->assertRaw($table_header . '</th>', format_string('%table_header table header was found.', array('%table_header' => $table_header)));
    }

    // "Add new field" and "Re-use existing field" aren't a table heading so just
    // test the text.
    foreach (array('Add new field', 'Re-use existing field') as $element) {
      $this->assertText($element, format_string('"@element" was found.', array('@element' => $element)));
    }
  }

  /**
   * Tests adding a new field.
   *
   * @todo Assert properties can bet set in the form and read back in $field and
   * $instances.
   */
  function createField() {
    // Create a test field.
    $edit = array(
      'fields[_add_new_field][label]' => $this->field_label,
      'fields[_add_new_field][field_name]' => $this->field_name_input,
    );
    $this->fieldUIAddNewField('admin/structure/types/manage/' . $this->type, $edit);

    // Assert the field appears in the "re-use existing field" section for
    // different entity types; e.g. if a field was added in a node entity, it
    // should also appear in the 'taxonomy term' entity.
    $vocabulary = entity_load('taxonomy_vocabulary', 'tags');
    $this->drupalGet('admin/structure/taxonomy/manage/' . $vocabulary->id() . '/fields');
    $this->assertTrue($this->xpath('//select[@name="fields[_add_existing_field][field_name]"]//option[@value="' . $this->field_name . '"]'), 'Existing field was found in taxonomy term fields.');
  }

  /**
   * Tests editing an existing field.
   */
  function updateField() {
    $instance_id = 'node.' . $this->type . '.' . $this->field_name;
    // Go to the field edit page.
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/fields/' . $instance_id . '/field');

    // Populate the field settings with new settings.
    $string = 'updated dummy test string';
    $edit = array(
      'field[settings][test_field_setting]' => $string,
    );
    $this->drupalPost(NULL, $edit, t('Save field settings'));

    // Go to the field instance edit page.
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/fields/' . $instance_id);
    $edit = array(
      'instance[settings][test_instance_setting]' => $string,
    );
    $this->drupalPost(NULL, $edit, t('Save settings'));

    // Assert the field settings are correct.
    $this->assertFieldSettings($this->type, $this->field_name, $string);

    // Assert redirection back to the "manage fields" page.
    $this->assertUrl('admin/structure/types/manage/' . $this->type . '/fields');
  }

  /**
   * Tests adding an existing field in another content type.
   */
  function addExistingField() {
    // Check "Re-use existing field" appears.
    $this->drupalGet('admin/structure/types/manage/page/fields');
    $this->assertRaw(t('Re-use existing field'), '"Re-use existing field" was found.');

    // Check that the list of options respects entity type restrictions on
    // fields. The 'comment' field is restricted to the 'comment' entity type
    // and should not appear in the list.
    $this->assertFalse($this->xpath('//select[@id="edit-add-existing-field-field-name"]//option[@value="comment"]'), 'The list of options respects entity type restrictions.');

    // Add a new field based on an existing field.
    $edit = array(
      'fields[_add_existing_field][label]' => $this->field_label . '_2',
      'fields[_add_existing_field][field_name]' => $this->field_name,
    );
    $this->fieldUIAddExistingField("admin/structure/types/manage/page", $edit);
  }

  /**
   * Tests the cardinality settings of a field.
   *
   * We do not test if the number can be submitted with anything else than a
   * numeric value. That is tested already in FormTest::testNumber().
   */
  function cardinalitySettings() {
    $field_edit_path = 'admin/structure/types/manage/article/fields/node.article.body/field';

    // Assert the cardinality other field cannot be empty when cardinality is
    // set to 'number'.
    $edit = array(
      'field[cardinality]' => 'number',
      'field[cardinality_number]' => '',
    );
    $this->drupalPost($field_edit_path, $edit, t('Save field settings'));
    $this->assertText('Number of values is required.');

    // Submit a custom number.
    $edit = array(
      'field[cardinality]' => 'number',
      'field[cardinality_number]' => 6,
    );
    $this->drupalPost($field_edit_path, $edit, t('Save field settings'));
    $this->assertText('Updated field Body field settings.');
    $this->drupalGet($field_edit_path);
    $this->assertFieldByXPath("//select[@name='field[cardinality]']", 'number');
    $this->assertFieldByXPath("//input[@name='field[cardinality_number]']", 6);

    // Set to unlimited.
    $edit = array(
      'field[cardinality]' => FIELD_CARDINALITY_UNLIMITED,
    );
    $this->drupalPost($field_edit_path, $edit, t('Save field settings'));
    $this->assertText('Updated field Body field settings.');
    $this->drupalGet($field_edit_path);
    $this->assertFieldByXPath("//select[@name='field[cardinality]']", FIELD_CARDINALITY_UNLIMITED);
    $this->assertFieldByXPath("//input[@name='field[cardinality_number]']", 1);
  }

  /**
   * Asserts field settings are as expected.
   *
   * @param $bundle
   *   The bundle name for the instance.
   * @param $field_name
   *   The field name for the instance.
   * @param $string
   *   The settings text.
   * @param $entity_type
   *   The entity type for the instance.
   */
  function assertFieldSettings($bundle, $field_name, $string = 'dummy test string', $entity_type = 'node') {
    // Reset the fields info.
    field_info_cache_clear();
    // Assert field settings.
    $field = field_info_field($field_name);
    $this->assertTrue($field['settings']['test_field_setting'] == $string, 'Field settings were found.');

    // Assert instance settings.
    $instance = field_info_instance($entity_type, $field_name, $bundle);
    $this->assertTrue($instance['settings']['test_instance_setting'] == $string, 'Field instance settings were found.');
  }

  /**
   * Tests that the 'field_prefix' setting works on Field UI.
   */
  function testFieldPrefix() {
    // Change default field prefix.
    $field_prefix = strtolower($this->randomName(10));
    \Drupal::config('field_ui.settings')->set('field_prefix', $field_prefix)->save();

    // Create a field input and label exceeding the new maxlength, which is 22.
    $field_exceed_max_length_label = $this->randomString(23);
    $field_exceed_max_length_input = $this->randomName(23);

    // Try to create the field.
    $edit = array(
      'fields[_add_new_field][label]' => $field_exceed_max_length_label,
      'fields[_add_new_field][field_name]' => $field_exceed_max_length_input,
    );
    $this->drupalPost('admin/structure/types/manage/' . $this->type . '/fields', $edit, t('Save'));
    $this->assertText('New field name cannot be longer than 22 characters but is currently 23 characters long.');

    // Create a valid field.
    $edit = array(
      'fields[_add_new_field][label]' => $this->field_label,
      'fields[_add_new_field][field_name]' => $this->field_name_input,
    );
    $this->fieldUIAddNewField('admin/structure/types/manage/' . $this->type, $edit);
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/fields/node.' . $this->type . '.' . $field_prefix . $this->field_name_input);
    $this->assertText(format_string('@label settings for @type', array('@label' => $this->field_label, '@type' => $this->type)));
  }

  /**
   * Tests that default value is correctly validated and saved.
   */
  function testDefaultValue() {
    // Create a test field and instance.
    $field_name = 'test';
    entity_create('field_entity', array(
      'field_name' => $field_name,
      'type' => 'test_field'
    ))->save();
    $instance = entity_create('field_instance', array(
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $this->type,
    ));
    $instance->save();

    entity_get_form_display('node', $this->type, 'default')
      ->setComponent($field_name)
      ->save();

    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $admin_path = 'admin/structure/types/manage/' . $this->type . '/fields/' . $instance->id();
    $element_id = "edit-$field_name-$langcode-0-value";
    $element_name = "{$field_name}[$langcode][0][value]";
    $this->drupalGet($admin_path);
    $this->assertFieldById($element_id, '', 'The default value widget was empty.');

    // Check that invalid default values are rejected.
    $edit = array($element_name => '-1');
    $this->drupalPost($admin_path, $edit, t('Save settings'));
    $this->assertText("$field_name does not accept the value -1", 'Form vaildation failed.');

    // Check that the default value is saved.
    $edit = array($element_name => '1');
    $this->drupalPost($admin_path, $edit, t('Save settings'));
    $this->assertText("Saved $field_name configuration", 'The form was successfully submitted.');
    field_info_cache_clear();
    $instance = field_info_instance('node', $field_name, $this->type);
    $this->assertEqual($instance['default_value'], array(array('value' => 1)), 'The default value was correctly saved.');

    // Check that the default value shows up in the form
    $this->drupalGet($admin_path);
    $this->assertFieldById($element_id, '1', 'The default value widget was displayed with the correct value.');

    // Check that the default value can be emptied.
    $edit = array($element_name => '');
    $this->drupalPost(NULL, $edit, t('Save settings'));
    $this->assertText("Saved $field_name configuration", 'The form was successfully submitted.');
    field_info_cache_clear();
    $instance = field_info_instance('node', $field_name, $this->type);
    $this->assertEqual($instance['default_value'], NULL, 'The default value was correctly saved.');

    // Change the widget to TestFieldWidgetNoDefault.
    entity_get_form_display($instance['entity_type'], $instance['bundle'], 'default')
      ->setComponent($field_name, array(
        'type' => 'test_field_widget_no_default',
      ))
      ->save();

    $this->drupalGet($admin_path);
    $this->assertNoFieldById($element_id, '', 'No default value was possible for widget that disables default value.');
  }

  /**
   * Tests that deletion removes fields and instances as expected.
   */
  function testDeleteField() {
    // Create a new field.
    $bundle_path1 = 'admin/structure/types/manage/' . $this->type;
    $edit1 = array(
      'fields[_add_new_field][label]' => $this->field_label,
      'fields[_add_new_field][field_name]' => $this->field_name_input,
    );
    $this->fieldUIAddNewField($bundle_path1, $edit1);

    // Create an additional node type.
    $type_name2 = strtolower($this->randomName(8)) . '_test';
    $type2 = $this->drupalCreateContentType(array('name' => $type_name2, 'type' => $type_name2));
    $type_name2 = $type2->type;

    // Add an instance to the second node type.
    $bundle_path2 = 'admin/structure/types/manage/' . $type_name2;
    $edit2 = array(
      'fields[_add_existing_field][label]' => $this->field_label,
      'fields[_add_existing_field][field_name]' => $this->field_name,
    );
    $this->fieldUIAddExistingField($bundle_path2, $edit2);

    // Delete the first instance.
    $this->fieldUIDeleteField($bundle_path1, "node.$this->type.$this->field_name", $this->field_label, $this->type);

    // Reset the fields info.
    field_info_cache_clear();
    // Check that the field instance was deleted.
    $this->assertNull(field_info_instance('node', $this->field_name, $this->type), 'Field instance was deleted.');
    // Check that the field was not deleted
    $this->assertNotNull(field_info_field($this->field_name), 'Field was not deleted.');

    // Delete the second instance.
    $this->fieldUIDeleteField($bundle_path2, "node.$type_name2.$this->field_name", $this->field_label, $type_name2);

    // Reset the fields info.
    field_info_cache_clear();
    // Check that the field instance was deleted.
    $this->assertNull(field_info_instance('node', $this->field_name, $type_name2), 'Field instance was deleted.');
    // Check that the field was deleted too.
    $this->assertNull(field_info_field($this->field_name), 'Field was deleted.');
  }

  /**
   * Tests that Field UI respects locked fields.
   */
  function testLockedField() {
    // Create a locked field and attach it to a bundle. We need to do this
    // programatically as there's no way to create a locked field through UI.
    $field = entity_create('field_entity', array(
      'field_name' => strtolower($this->randomName(8)),
      'type' => 'test_field',
      'cardinality' => 1,
      'locked' => TRUE
    ));
    $field->save();
    entity_create('field_instance', array(
      'field_uuid' => $field->uuid,
      'entity_type' => 'node',
      'bundle' => $this->type,
    ))->save();
    entity_get_form_display('node', $this->type, 'default')
      ->setComponent($field->id, array(
        'type' => 'test_field_widget',
      ))
      ->save();

    // Check that the links for edit and delete are not present.
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/fields');
    $locked = $this->xpath('//tr[@id=:field_name]/td[4]', array(':field_name' => $field->id()));
    $this->assertTrue(in_array('Locked', $locked), 'Field is marked as Locked in the UI');
    $edit_link = $this->xpath('//tr[@id=:field_name]/td[4]', array(':field_name' => $field->id()));
    $this->assertFalse(in_array('edit', $edit_link), 'Edit option for locked field is not present the UI');
    $delete_link = $this->xpath('//tr[@id=:field_name]/td[4]', array(':field_name' => $field->id()));
    $this->assertFalse(in_array('delete', $delete_link), 'Delete option for locked field is not present the UI');
  }

  /**
   * Tests that Field UI respects the 'no_ui' flag in the field type definition.
   */
  function testHiddenFields() {
    $bundle_path = 'admin/structure/types/manage/' . $this->type . '/fields/';

    // Check that the field type is not available in the 'add new field' row.
    $this->drupalGet($bundle_path);
    $this->assertFalse($this->xpath('//select[@id="edit-add-new-field-type"]//option[@value="hidden_test_field"]'), "The 'add new field' select respects field types 'no_ui' property.");

    // Create a field and an instance programmatically.
    $field_name = 'hidden_test_field';
    entity_create('field_entity', array('field_name' => $field_name, 'type' => $field_name))->save();
    $instance = array(
      'field_name' => $field_name,
      'bundle' => $this->type,
      'entity_type' => 'node',
      'label' => t('Hidden field'),
    );
    entity_create('field_instance', $instance)->save();
    entity_get_form_display('node', $this->type, 'default')
      ->setComponent($field_name)
      ->save();
    $this->assertTrue(field_read_instance('node', $field_name, $this->type), format_string('An instance of the field %field was created programmatically.', array('%field' => $field_name)));

    // Check that the newly added instance appears on the 'Manage Fields'
    // screen.
    $this->drupalGet($bundle_path);
    $this->assertFieldByXPath('//table[@id="field-overview"]//td[1]', $instance['label'], 'Field was created and appears in the overview page.');

    // Check that the instance does not appear in the 're-use existing field' row
    // on other bundles.
    $bundle_path = 'admin/structure/types/manage/article/fields/';
    $this->drupalGet($bundle_path);
    $this->assertFalse($this->xpath('//select[@id="edit-add-existing-field-field-name"]//option[@value=:field_name]', array(':field_name' => $field_name)), "The 're-use existing field' select respects field types 'no_ui' property.");
  }

  /**
   * Tests renaming a bundle.
   */
  function testRenameBundle() {
    $type2 = strtolower($this->randomName(8)) . '_test';

    $options = array(
      'type' => $type2,
    );
    $this->drupalPost('admin/structure/types/manage/' . $this->type, $options, t('Save content type'));
    $this->manageFieldsPage($type2);
  }

  /**
   * Tests that a duplicate field name is caught by validation.
   */
  function testDuplicateFieldName() {
    // field_tags already exists, so we're expecting an error when trying to
    // create a new field with the same name.
    $edit = array(
      'fields[_add_new_field][field_name]' => 'tags',
      'fields[_add_new_field][label]' => $this->randomName(),
      'fields[_add_new_field][type]' => 'taxonomy_term_reference',
    );
    $url = 'admin/structure/types/manage/' . $this->type . '/fields';
    $this->drupalPost($url, $edit, t('Save'));

    $this->assertText(t('The machine-readable name is already in use. It must be unique.'));
    $this->assertUrl($url, array(), 'Stayed on the same page.');
  }

  /**
   * Tests that deletion removes fields and instances as expected for a term.
   */
  function testDeleteTaxonomyField() {
    // Create a new field.
    $bundle_path = 'admin/structure/taxonomy/manage/tags';
    $edit1 = array(
      'fields[_add_new_field][label]' => $this->field_label,
      'fields[_add_new_field][field_name]' => $this->field_name_input,
    );
    $this->fieldUIAddNewField($bundle_path, $edit1);

    // Delete the field.
    $this->fieldUIDeleteField($bundle_path, "taxonomy_term.tags.$this->field_name", $this->field_label, 'Tags');

    // Reset the fields info.
    field_info_cache_clear();
    // Check that the field instance was deleted.
    $this->assertNull(field_info_instance('taxonomy_term', $this->field_name, 'tags'), 'Field instance was deleted.');
    // Check that the field was deleted too.
    $this->assertNull(field_info_field($this->field_name), 'Field was deleted.');
  }

  /**
   * Tests that help descriptions render valid HTML.
   */
  function testHelpDescriptions() {
    // Create an image field
    entity_create('field_entity', array(
      'field_name' => 'field_image',
      'type' => 'image',
    ))->save();

    entity_create('field_instance', array(
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'label' => 'Image',
      'bundle' => 'article',
    ))->save();

    entity_get_form_display('node', 'article', 'default')->setComponent('field_image')->save();

    $edit = array(
      'instance[description]' => '<strong>Test with an upload field.',
    );
    $this->drupalPost('admin/structure/types/manage/article/fields/node.article.field_image', $edit, t('Save settings'));

    $edit = array(
      'instance[description]' => '<em>Test with a non upload field.',
    );
    $this->drupalPost('admin/structure/types/manage/article/fields/node.article.field_tags', $edit, t('Save settings'));

    $this->drupalGet('node/add/article');
    $this->assertRaw('<strong>Test with an upload field.</strong>');
    $this->assertRaw('<em>Test with a non upload field.</em>');
  }

  /**
   * Tests that the field list administration page operates correctly.
   */
  function fieldListAdminPage() {
    $this->drupalGet('admin/reports/fields');
    $this->assertText($this->field_name, 'Field name is displayed in field list.');
    $this->assertTrue($this->assertLinkByHref('admin/structure/types/manage/' . $this->type . '/fields'), 'Link to content type using field is displayed in field list.');
  }
}
