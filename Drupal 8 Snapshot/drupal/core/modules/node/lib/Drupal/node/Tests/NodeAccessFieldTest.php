<?php

/**
 * @file
 * Definition of Drupal\node\Tests\NodeAccessFieldTest.
 */

namespace Drupal\node\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests the interaction of the node access system with fields.
 */
class NodeAccessFieldTest extends NodeTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node_access_test', 'field_ui');

  /**
   * A user with permission to bypass access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin_user;

  /**
   * A user with permission to manage content types and fields.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $content_admin_user;

  /**
   * The name of the created field.
   *
   * @var string
   */
  protected $field_name;

  public static function getInfo() {
    return array(
      'name' => 'Node access and fields',
      'description' => 'Tests the interaction of the node access system with fields.',
      'group' => 'Node',
    );
  }

  public function setUp() {
    parent::setUp();

    node_access_rebuild();

    // Create some users.
    $this->admin_user = $this->drupalCreateUser(array('access content', 'bypass node access'));
    $this->content_admin_user = $this->drupalCreateUser(array('access content', 'administer content types', 'administer node fields'));

    // Add a custom field to the page content type.
    $this->field_name = drupal_strtolower($this->randomName() . '_field_name');
    entity_create('field_entity', array(
      'field_name' => $this->field_name,
      'type' => 'text'
    ))->save();
    entity_create('field_instance', array(
      'field_name' => $this->field_name,
      'entity_type' => 'node',
      'bundle' => 'page',
    ))->save();
    entity_get_display('node', 'page', 'default')
      ->setComponent($this->field_name)
      ->save();
    entity_get_form_display('node', 'page', 'default')
      ->setComponent($this->field_name)
      ->save();
  }

  /**
   * Tests administering fields when node access is restricted.
   */
  function testNodeAccessAdministerField() {
    // Create a page node.
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $field_data = array();
    $value = $field_data[0]['value'] = $this->randomName();
    $node = $this->drupalCreateNode(array($this->field_name => $field_data));

    // Log in as the administrator and confirm that the field value is present.
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('node/' . $node->id());
    $this->assertText($value, 'The saved field value is visible to an administrator.');

    // Log in as the content admin and try to view the node.
    $this->drupalLogin($this->content_admin_user);
    $this->drupalGet('node/' . $node->id());
    $this->assertText('Access denied', 'Access is denied for the content admin.');

    // Modify the field default as the content admin.
    $edit = array();
    $default = 'Sometimes words have two meanings';
    $edit["{$this->field_name}[$langcode][0][value]"] = $default;
    $this->drupalPost(
      "admin/structure/types/manage/page/fields/node.page.{$this->field_name}",
      $edit,
      t('Save settings')
    );

    // Log in as the administrator.
    $this->drupalLogin($this->admin_user);

    // Confirm that the existing node still has the correct field value.
    $this->drupalGet('node/' . $node->id());
    $this->assertText($value, 'The original field value is visible to an administrator.');

    // Confirm that the new default value appears when creating a new node.
    $this->drupalGet('node/add/page');
    $this->assertRaw($default, 'The updated default value is displayed when creating a new node.');
  }
}
