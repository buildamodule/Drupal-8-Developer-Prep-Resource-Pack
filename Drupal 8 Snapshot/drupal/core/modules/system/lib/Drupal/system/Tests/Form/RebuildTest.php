<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Form\RebuildTest.
 */

namespace Drupal\system\Tests\Form;

use Drupal\simpletest\WebTestBase;

/**
 * Tests form rebuilding.
 *
 * @todo Add tests for other aspects of form rebuilding.
 */
class RebuildTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('form_test');

  public static function getInfo() {
    return array(
      'name' => 'Form rebuilding',
      'description' => 'Tests functionality of drupal_rebuild_form().',
      'group' => 'Form API',
    );
  }

  function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));

    $this->web_user = $this->drupalCreateUser(array('access content'));
    $this->drupalLogin($this->web_user);
  }

  /**
   * Tests preservation of values.
   */
  function testRebuildPreservesValues() {
    $edit = array(
      'checkbox_1_default_off' => TRUE,
      'checkbox_1_default_on' => FALSE,
      'text_1' => 'foo',
    );
    $this->drupalPost('form-test/form-rebuild-preserve-values', $edit, 'Add more');

    // Verify that initial elements retained their submitted values.
    $this->assertFieldChecked('edit-checkbox-1-default-off', 'A submitted checked checkbox retained its checked state during a rebuild.');
    $this->assertNoFieldChecked('edit-checkbox-1-default-on', 'A submitted unchecked checkbox retained its unchecked state during a rebuild.');
    $this->assertFieldById('edit-text-1', 'foo', 'A textfield retained its submitted value during a rebuild.');

    // Verify that newly added elements were initialized with their default values.
    $this->assertFieldChecked('edit-checkbox-2-default-on', 'A newly added checkbox was initialized with a default checked state.');
    $this->assertNoFieldChecked('edit-checkbox-2-default-off', 'A newly added checkbox was initialized with a default unchecked state.');
    $this->assertFieldById('edit-text-2', 'DEFAULT 2', 'A newly added textfield was initialized with its default value.');
  }

  /**
   * Tests that a form's action is retained after an Ajax submission.
   *
   * The 'action' attribute of a form should not change after an Ajax submission
   * followed by a non-Ajax submission, which triggers a validation error.
   */
  function testPreserveFormActionAfterAJAX() {
    // Create a multi-valued field for 'page' nodes to use for Ajax testing.
    $field_name = 'field_ajax_test';
    $field = array(
      'field_name' => $field_name,
      'type' => 'text',
      'cardinality' => FIELD_CARDINALITY_UNLIMITED,
    );
    entity_create('field_entity', $field)->save();
    $instance = array(
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'page',
    );
    entity_create('field_instance', $instance)->save();
    entity_get_form_display('node', 'page', 'default')
      ->setComponent($field_name, array('type' => 'text_test'))
      ->save();

    // Log in a user who can create 'page' nodes.
    $this->web_user = $this->drupalCreateUser(array('create page content'));
    $this->drupalLogin($this->web_user);

    // Get the form for adding a 'page' node. Submit an "add another item" Ajax
    // submission and verify it worked by ensuring the updated page has two text
    // field items in the field for which we just added an item.
    $this->drupalGet('node/add/page');
    $this->drupalPostAJAX(NULL, array(), array('field_ajax_test_add_more' => t('Add another item')), 'system/ajax', array(), array(), 'page-node-form');
    $this->assert(count($this->xpath('//div[contains(@class, "field-name-field-ajax-test")]//input[@type="text"]')) == 2, 'AJAX submission succeeded.');

    // Submit the form with the non-Ajax "Save" button, leaving the title field
    // blank to trigger a validation error, and ensure that a validation error
    // occurred, because this test is for testing what happens when a form is
    // re-rendered without being re-built, which is what happens when there's
    // a validation error.
    $this->drupalPost(NULL, array(), t('Save'));
    $this->assertText('Title field is required.', 'Non-AJAX submission correctly triggered a validation error.');

    // Ensure that the form contains two items in the multi-valued field, so we
    // know we're testing a form that was correctly retrieved from cache.
    $this->assert(count($this->xpath('//form[contains(@id, "page-node-form")]//div[contains(@class, "form-item-field-ajax-test")]//input[@type="text"]')) == 2, 'Form retained its state from cache.');

    // Ensure that the form's action is correct.
    $forms = $this->xpath('//form[contains(@class, "node-page-form")]');
    $this->assert(count($forms) == 1 && $forms[0]['action'] == url('node/add/page'), 'Re-rendered form contains the correct action value.');
  }
}
