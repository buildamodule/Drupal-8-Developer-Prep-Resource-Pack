<?php

/**
 * @file
 * Definition of Drupal\filter\Tests\FilterFormatAccessTest.
 */

namespace Drupal\filter\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the filter format access functionality in the Filter module.
 */
class FilterFormatAccessTest extends WebTestBase {
  /**
   * A user with administrative permissions.
   *
   * @var object
   */
  protected $admin_user;

  /**
   * A user with 'administer filters' permission.
   *
   * @var object
   */
  protected $filter_admin_user;

  /**
   * A user with permission to create and edit own content.
   *
   * @var object
   */
  protected $web_user;

  /**
   * An object representing an allowed text format.
   *
   * @var object
   */
  protected $allowed_format;

  /**
   * An object representing a secondary allowed text format.
   *
   * @var object
   */
  protected $second_allowed_format;

  /**
   * An object representing a disallowed text format.
   *
   * @var object
   */
  protected $disallowed_format;

  public static function getInfo() {
    return array(
      'name' => 'Filter format access',
      'description' => 'Tests access to text formats.',
      'group' => 'Filter',
    );
  }

  function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));

    // Create a user who can administer text formats, but does not have
    // specific permission to use any of them.
    $this->filter_admin_user = $this->drupalCreateUser(array(
      'administer filters',
      'create page content',
      'edit any page content',
    ));

    // Create three text formats. Two text formats are created for all users so
    // that the drop-down list appears for all tests.
    $this->drupalLogin($this->filter_admin_user);
    $formats = array();
    for ($i = 0; $i < 3; $i++) {
      $edit = array(
        'format' => drupal_strtolower($this->randomName()),
        'name' => $this->randomName(),
      );
      $this->drupalPost('admin/config/content/formats/add', $edit, t('Save configuration'));
      $this->resetFilterCaches();
      $formats[] = filter_format_load($edit['format']);
    }
    list($this->allowed_format, $this->second_allowed_format, $this->disallowed_format) = $formats;
    $this->drupalLogout();

    // Create a regular user with access to two of the formats.
    $this->web_user = $this->drupalCreateUser(array(
      'create page content',
      'edit any page content',
      filter_permission_name($this->allowed_format),
      filter_permission_name($this->second_allowed_format),
    ));

    // Create an administrative user who has access to use all three formats.
    $this->admin_user = $this->drupalCreateUser(array(
      'administer filters',
      'create page content',
      'edit any page content',
      filter_permission_name($this->allowed_format),
      filter_permission_name($this->second_allowed_format),
      filter_permission_name($this->disallowed_format),
    ));
  }

  /**
   * Tests the Filter format access permissions functionality.
   */
  function testFormatPermissions() {
    // Make sure that a regular user only has access to the text formats for
    // which they were granted access.
    $this->assertTrue(filter_access($this->allowed_format, $this->web_user), 'A regular user has access to a text format they were granted access to.');
    $this->assertFalse(filter_access($this->disallowed_format, $this->web_user), 'A regular user does not have access to a text format they were not granted access to.');
    $this->assertTrue(filter_access(filter_format_load(filter_fallback_format()), $this->web_user), 'A regular user has access to the fallback format.');

    // Perform similar checks as above, but now against the entire list of
    // available formats for this user.
    $this->assertTrue(in_array($this->allowed_format->format, array_keys(filter_formats($this->web_user))), 'The allowed format appears in the list of available formats for a regular user.');
    $this->assertFalse(in_array($this->disallowed_format->format, array_keys(filter_formats($this->web_user))), 'The disallowed format does not appear in the list of available formats for a regular user.');
    $this->assertTrue(in_array(filter_fallback_format(), array_keys(filter_formats($this->web_user))), 'The fallback format appears in the list of available formats for a regular user.');

    // Make sure that a regular user only has permission to use the format
    // they were granted access to.
    $this->assertTrue(user_access(filter_permission_name($this->allowed_format), $this->web_user), 'A regular user has permission to use the allowed text format.');
    $this->assertFalse(user_access(filter_permission_name($this->disallowed_format), $this->web_user), 'A regular user does not have permission to use the disallowed text format.');

    // Make sure that the allowed format appears on the node form and that
    // the disallowed format does not.
    $this->drupalLogin($this->web_user);
    $this->drupalGet('node/add/page');
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $elements = $this->xpath('//select[@name=:name]/option', array(
      ':name' => "body[$langcode][0][format]",
      ':option' => $this->allowed_format->format,
    ));
    $options = array();
    foreach ($elements as $element) {
      $options[(string) $element['value']] = $element;
    }
    $this->assertTrue(isset($options[$this->allowed_format->format]), 'The allowed text format appears as an option when adding a new node.');
    $this->assertFalse(isset($options[$this->disallowed_format->format]), 'The disallowed text format does not appear as an option when adding a new node.');
    $this->assertFalse(isset($options[filter_fallback_format()]), 'The fallback format does not appear as an option when adding a new node.');

    // Check regular user access to the filter tips pages.
    $this->drupalGet('filter/tips/' . $this->allowed_format->format);
    $this->assertResponse(200);
    $this->drupalGet('filter/tips/' . $this->disallowed_format->format);
    $this->assertResponse(403);
    $this->drupalGet('filter/tips/' . filter_fallback_format());
    $this->assertResponse(200);
    $this->drupalGet('filter/tips/invalid-format');
    $this->assertResponse(404);

    // Check admin user access to the filter tips pages.
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('filter/tips/' . $this->allowed_format->format);
    $this->assertResponse(200);
    $this->drupalGet('filter/tips/' . $this->disallowed_format->format);
    $this->assertResponse(200);
    $this->drupalGet('filter/tips/' . filter_fallback_format());
    $this->assertResponse(200);
    $this->drupalGet('filter/tips/invalid-format');
    $this->assertResponse(404);
  }

  /**
   * Tests if text format is available to a role.
   */
  function testFormatRoles() {
    // Get the role ID assigned to the regular user.
    $roles = $this->web_user->getRoles();
    $rid = $roles[0];

    // Check that this role appears in the list of roles that have access to an
    // allowed text format, but does not appear in the list of roles that have
    // access to a disallowed text format.
    $this->assertTrue(in_array($rid, array_keys(filter_get_roles_by_format($this->allowed_format))), 'A role which has access to a text format appears in the list of roles that have access to that format.');
    $this->assertFalse(in_array($rid, array_keys(filter_get_roles_by_format($this->disallowed_format))), 'A role which does not have access to a text format does not appear in the list of roles that have access to that format.');

    // Check that the correct text format appears in the list of formats
    // available to that role.
    $this->assertTrue(in_array($this->allowed_format->format, array_keys(filter_get_formats_by_role($rid))), 'A text format which a role has access to appears in the list of formats available to that role.');
    $this->assertFalse(in_array($this->disallowed_format->format, array_keys(filter_get_formats_by_role($rid))), 'A text format which a role does not have access to does not appear in the list of formats available to that role.');

    // Check that the fallback format is always allowed.
    $this->assertEqual(filter_get_roles_by_format(filter_format_load(filter_fallback_format())), user_role_names(), 'All roles have access to the fallback format.');
    $this->assertTrue(in_array(filter_fallback_format(), array_keys(filter_get_formats_by_role($rid))), 'The fallback format appears in the list of allowed formats for any role.');
  }

  /**
   * Tests editing a page using a disallowed text format.
   *
   * Verifies that regular users and administrators are able to edit a page, but
   * not allowed to change the fields which use an inaccessible text format.
   * Also verifies that fields which use a text format that does not exist can
   * be edited by administrators only, but that the administrator is forced to
   * choose a new format before saving the page.
   */
  function testFormatWidgetPermissions() {
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $title_key = "title";
    $body_value_key = "body[$langcode][0][value]";
    $body_format_key = "body[$langcode][0][format]";

    // Create node to edit.
    $this->drupalLogin($this->admin_user);
    $edit = array();
    $edit['title'] = $this->randomName(8);
    $edit[$body_value_key] = $this->randomName(16);
    $edit[$body_format_key] = $this->disallowed_format->format;
    $this->drupalPost('node/add/page', $edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($edit['title']);

    // Try to edit with a less privileged user.
    $this->drupalLogin($this->web_user);
    $this->drupalGet('node/' . $node->id());
    $this->clickLink(t('Edit'));

    // Verify that body field is read-only and contains replacement value.
    $this->assertFieldByXPath("//textarea[@name='$body_value_key' and @disabled='disabled']", t('This field has been disabled because you do not have sufficient permissions to edit it.'), 'Text format access denied message found.');

    // Verify that title can be changed, but preview displays original body.
    $new_edit = array();
    $new_edit['title'] = $this->randomName(8);
    $this->drupalPost(NULL, $new_edit, t('Preview'));
    $this->assertText($edit[$body_value_key], 'Old body found in preview.');

    // Save and verify that only the title was changed.
    $this->drupalPost(NULL, $new_edit, t('Save'));
    $this->assertNoText($edit['title'], 'Old title not found.');
    $this->assertText($new_edit['title'], 'New title found.');
    $this->assertText($edit[$body_value_key], 'Old body found.');

    // Check that even an administrator with "administer filters" permission
    // cannot edit the body field if they do not have specific permission to
    // use its stored format. (This must be disallowed so that the
    // administrator is never forced to switch the text format to something
    // else.)
    $this->drupalLogin($this->filter_admin_user);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertFieldByXPath("//textarea[@name='$body_value_key' and @disabled='disabled']", t('This field has been disabled because you do not have sufficient permissions to edit it.'), 'Text format access denied message found.');

    // Disable the text format used above.
    $this->disallowed_format->disable()->save();
    $this->resetFilterCaches();

    // Log back in as the less privileged user and verify that the body field
    // is still disabled, since the less privileged user should not be able to
    // edit content that does not have an assigned format.
    $this->drupalLogin($this->web_user);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertFieldByXPath("//textarea[@name='$body_value_key' and @disabled='disabled']", t('This field has been disabled because you do not have sufficient permissions to edit it.'), 'Text format access denied message found.');

    // Log back in as the filter administrator and verify that the body field
    // can be edited.
    $this->drupalLogin($this->filter_admin_user);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertNoFieldByXPath("//textarea[@name='$body_value_key' and @disabled='disabled']", NULL, 'Text format access denied message not found.');
    $this->assertFieldByXPath("//select[@name='$body_format_key']", NULL, 'Text format selector found.');

    // Verify that trying to save the node without selecting a new text format
    // produces an error message, and does not result in the node being saved.
    $old_title = $new_edit['title'];
    $new_title = $this->randomName(8);
    $edit = array('title' => $new_title);
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save'));
    $this->assertText(t('!name field is required.', array('!name' => t('Text format'))), 'Error message is displayed.');
    $this->drupalGet('node/' . $node->id());
    $this->assertText($old_title, 'Old title found.');
    $this->assertNoText($new_title, 'New title not found.');

    // Now select a new text format and make sure the node can be saved.
    $edit[$body_format_key] = filter_fallback_format();
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save'));
    $this->assertUrl('node/' . $node->id());
    $this->assertText($new_title, 'New title found.');
    $this->assertNoText($old_title, 'Old title not found.');

    // Switch the text format to a new one, then disable that format and all
    // other formats on the site (leaving only the fallback format).
    $this->drupalLogin($this->admin_user);
    $edit = array($body_format_key => $this->allowed_format->format);
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save'));
    $this->assertUrl('node/' . $node->id());
    foreach (filter_formats() as $format) {
      if ($format->format != filter_fallback_format()) {
        $format->disable()->save();
      }
    }

    // Since there is now only one available text format, the widget for
    // selecting a text format would normally not display when the content is
    // edited. However, we need to verify that the filter administrator still
    // is forced to make a conscious choice to reassign the text to a different
    // format.
    $this->drupalLogin($this->filter_admin_user);
    $old_title = $new_title;
    $new_title = $this->randomName(8);
    $edit = array('title' => $new_title);
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save'));
    $this->assertText(t('!name field is required.', array('!name' => t('Text format'))), 'Error message is displayed.');
    $this->drupalGet('node/' . $node->id());
    $this->assertText($old_title, 'Old title found.');
    $this->assertNoText($new_title, 'New title not found.');
    $edit[$body_format_key] = filter_fallback_format();
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save'));
    $this->assertUrl('node/' . $node->id());
    $this->assertText($new_title, 'New title found.');
    $this->assertNoText($old_title, 'Old title not found.');
  }

  /**
   * Rebuilds text format and permission caches in the thread running the tests.
   */
  protected function resetFilterCaches() {
    filter_formats_reset();
    $this->checkPermissions(array(), TRUE);
  }
}
