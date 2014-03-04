<?php

/**
 * @file
 * Definition of Drupal\node\Tests\MultiStepNodeFormBasicOptionsTest.
 */

namespace Drupal\node\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;

/**
 * Tests basic options of multi-step node forms.
 */
class MultiStepNodeFormBasicOptionsTest extends NodeTestBase {

  /**
   * The field name to create.
   *
   * @var string
   */
  protected $field_name;

  public static function getInfo() {
    return array(
      'name' => 'Multistep node form basic options',
      'description' => 'Test the persistence of basic options through multiple steps.',
      'group' => 'Node',
    );
  }

  /**
   * Tests changing the default values of basic options to ensure they persist.
   */
  function testMultiStepNodeFormBasicOptions() {
    // Prepare a user to create the node.
    $web_user = $this->drupalCreateUser(array('administer nodes', 'create page content'));
    $this->drupalLogin($web_user);

    // Create an unlimited cardinality field.
    $this->field_name = drupal_strtolower($this->randomName());
    entity_create('field_entity', array(
      'field_name' => $this->field_name,
      'type' => 'text',
      'cardinality' => -1,
    ))->save();

    // Attach an instance of the field to the page content type.
    entity_create('field_instance', array(
      'field_name' => $this->field_name,
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => $this->randomName() . '_label',
      'settings' => array(
        'text_processing' => TRUE,
      ),
    ))->save();
    entity_get_form_display('node', 'page', 'default')
      ->setComponent($this->field_name, array(
        'type' => 'text_textfield',
      ))
      ->save();

    $langcode = Language::LANGCODE_NOT_SPECIFIED;

    $edit = array(
      'title' => 'a',
      'promote' => FALSE,
      'sticky' => 1,
      "{$this->field_name}[$langcode][0][value]" => $this->randomString(32),
    );
    $this->drupalPost('node/add/page', $edit, t('Add another item'));
    $this->assertNoFieldChecked('edit-promote', 'promote stayed unchecked');
    $this->assertFieldChecked('edit-sticky', 'sticky stayed checked');
  }

}
