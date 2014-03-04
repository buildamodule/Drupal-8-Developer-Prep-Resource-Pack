<?php

/**
 * @file
 * Definition of Drupal\filter\Tests\FilterHooksTest.
 */

namespace Drupal\filter\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;

/**
 * Tests for Filter's hook invocations.
 */
class FilterHooksTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'filter_test');

  public static function getInfo() {
    return array(
      'name' => 'Filter format hooks',
      'description' => 'Test hooks for text formats insert/update/disable.',
      'group' => 'Filter',
    );
  }

  /**
   * Tests hooks on format management.
   *
   * Tests that hooks run correctly on creating, editing, and deleting a text
   * format.
   */
  function testFilterHooks() {
    // Create content type, with underscores.
    $type_name = 'test_' . strtolower($this->randomName());
    $type = $this->drupalCreateContentType(array('name' => $type_name, 'type' => $type_name));
    $node_permission = "create $type_name content";

    $admin_user = $this->drupalCreateUser(array('administer filters', 'administer nodes', $node_permission));
    $this->drupalLogin($admin_user);

    // Add a text format.
    $name = $this->randomName();
    $edit = array();
    $edit['format'] = drupal_strtolower($this->randomName());
    $edit['name'] = $name;
    $edit['roles[' . DRUPAL_ANONYMOUS_RID . ']'] = 1;
    $this->drupalPost('admin/config/content/formats/add', $edit, t('Save configuration'));
    $this->assertRaw(t('Added text format %format.', array('%format' => $name)));
    $this->assertText('hook_filter_format_insert invoked.');

    $format_id = $edit['format'];

    // Update text format.
    $edit = array();
    $edit['roles[' . DRUPAL_AUTHENTICATED_RID . ']'] = 1;
    $this->drupalPost('admin/config/content/formats/manage/' . $format_id, $edit, t('Save configuration'));
    $this->assertRaw(t('The text format %format has been updated.', array('%format' => $name)));
    $this->assertText('hook_filter_format_update invoked.');

    // Use the format created.
    $language_not_specified = Language::LANGCODE_NOT_SPECIFIED;
    $title = $this->randomName(8);
    $edit = array(
      "title" => $title,
      "body[$language_not_specified][0][value]" => $this->randomName(32),
      "body[$language_not_specified][0][format]" => $format_id,
    );
    $this->drupalPost("node/add/{$type->type}", $edit, t('Save and publish'));
    $this->assertText(t('@type @title has been created.', array('@type' => $type_name, '@title' => $title)));

    // Disable the text format.
    $this->drupalPost('admin/config/content/formats/manage/' . $format_id . '/disable', array(), t('Disable'));
    $this->assertRaw(t('Disabled text format %format.', array('%format' => $name)));
    $this->assertText('hook_filter_format_disable invoked.');
  }
}
