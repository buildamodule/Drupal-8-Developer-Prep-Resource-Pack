<?php

/**
 * @file
 * Contains \Drupal\field\reEnableModuleFieldTest.
 */

namespace Drupal\field\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests a field is still present after it's module is disabled then re-enabled.
 */
class reEnableModuleFieldTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'field',
    'field_sql_storage',
    'node',
    // We use telephone module instead of test_field because test_field is
    // hidden and does not display on the admin/modules page.
    'telephone'
  );

  public static function getInfo() {
    return array(
      'name'  => 'Test field module re-enable',
      'description'  => "Test the behavior of a field module after being disabled and re-enabled.",
      'group' => 'Field types'
    );
  }

  function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'article'));
    $this->article_creator = $this->drupalCreateUser(array('create article content', 'edit own article content'));
    $this->drupalLogin($this->article_creator);
  }

  /**
   * Test the behavior of a field module after being disabled and re-enabled.
   */
  function testReEnabledField() {

    // Add a telephone field to the article content type.
    $field = entity_create('field_entity', array(
      'field_name' => 'field_telephone',
      'type' => 'telephone',
    ));
    $field->save();
    entity_create('field_instance', array(
      'field_name' => 'field_telephone',
      'label' => 'Telephone Number',
      'entity_type' => 'node',
      'bundle' => 'article',
    ))->save();

    entity_get_form_display('node', 'article', 'default')
      ->setComponent('field_telephone', array(
        'type' => 'telephone_default',
        'settings' => array(
          'placeholder' => '123-456-7890',
        ),
      ))
      ->save();

    entity_get_display('node', 'article', 'default')
      ->setComponent('field_telephone', array(
        'type' => 'telephone_link',
        'weight' => 1,
      ))
      ->save();

    // Display the article node form and verify the telephone widget is present.
    $this->drupalGet('node/add/article');
    $this->assertFieldByName("field_telephone[und][0][value]", '', 'Widget found.');

    // Submit an article node with a telephone field so data exist for the
    // field.
    $edit = array(
      "title" => $this->randomName(),
      "field_telephone[und][0][value]" => "123456789",
    );
    $this->drupalPost(NULL, $edit, t('Save'));
    $this->assertRaw('<a href="tel:123456789">');

    // Disable the telephone module and re-enable it.
    module_disable(array('telephone'));
    module_enable(array('telephone'));

    // Display the article creation form and verify the widget still exists.
    $this->drupalGet('node/add/article');
    $this->assertFieldByName("field_telephone[und][0][value]", '', 'Widget found.');

    // Test that the module can't be disabled from the UI while there is data
    // for it's fields.
    $admin_user = $this->drupalCreateUser(array('access administration pages', 'administer modules'));
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/modules');
    $this->assertText('Fields type(s) in use');
    $field->delete();
    $this->drupalGet('admin/modules');
    $this->assertText('Fields pending deletion');
    $this->cronRun();
    $this->assertNoText('Fields type(s) in use');
    $this->assertNoText('Fields pending deletion');

  }

}
