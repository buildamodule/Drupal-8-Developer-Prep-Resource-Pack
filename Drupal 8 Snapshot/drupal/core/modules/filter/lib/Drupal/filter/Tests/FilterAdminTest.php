<?php

/**
 * @file
 * Definition of Drupal\filter\Tests\FilterAdminTest.
 */

namespace Drupal\filter\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the administrative functionality of the Filter module.
 */
class FilterAdminTest extends WebTestBase {

  /**
   * The installation profile to use with this test.
   *
   * @var string
   */
  protected $profile = 'standard';

  public static function getInfo() {
    return array(
      'name' => 'Filter administration functionality',
      'description' => 'Thoroughly test the administrative interface of the filter module.',
      'group' => 'Filter',
    );
  }

  function setUp() {
    parent::setUp();

    // Create users.
    $basic_html_format = filter_format_load('basic_html');
    $restricted_html_format = filter_format_load('restricted_html');
    $full_html_format = filter_format_load('full_html');
    $this->admin_user = $this->drupalCreateUser(array(
      'administer filters',
      filter_permission_name($basic_html_format),
      filter_permission_name($restricted_html_format),
      filter_permission_name($full_html_format),
    ));

    $this->web_user = $this->drupalCreateUser(array('create page content', 'edit own page content'));
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Tests the format administration functionality.
   */
  function testFormatAdmin() {
    // Add text format.
    $this->drupalGet('admin/config/content/formats');
    $this->clickLink('Add text format');
    $format_id = drupal_strtolower($this->randomName());
    $name = $this->randomName();
    $edit = array(
      'format' => $format_id,
      'name' => $name,
    );
    $this->drupalPost(NULL, $edit, t('Save configuration'));

    // Verify default weight of the text format.
    $this->drupalGet('admin/config/content/formats');
    $this->assertFieldByName("formats[$format_id][weight]", 0, 'Text format weight was saved.');

    // Change the weight of the text format.
    $edit = array(
      "formats[$format_id][weight]" => 5,
    );
    $this->drupalPost('admin/config/content/formats', $edit, t('Save changes'));
    $this->assertFieldByName("formats[$format_id][weight]", 5, 'Text format weight was saved.');

    // Edit text format.
    $this->drupalGet('admin/config/content/formats');
    // Cannot use the assertNoLinkByHref method as it does partial url matching
    // and 'admin/config/content/formats/manage/' . $format_id . '/disable'
    // exists.
    // @todo: See https://drupal.org/node/2031223 for the above
    $edit_link = $this->xpath('//a[@href=:href]', array(
      ':href' => url('admin/config/content/formats/manage/' . $format_id)
    ));
    $this->assertTrue($edit_link, format_string('Link href %href found.',
      array('%href' => 'admin/config/content/formats/manage/' . $format_id)
    ));
    $this->drupalGet('admin/config/content/formats/manage/' . $format_id);
    $this->drupalPost(NULL, array(), t('Save configuration'));

    // Verify that the custom weight of the text format has been retained.
    $this->drupalGet('admin/config/content/formats');
    $this->assertFieldByName("formats[$format_id][weight]", 5, 'Text format weight was retained.');

    // Disable text format.
    $this->assertLinkByHref('admin/config/content/formats/manage/' . $format_id . '/disable');
    $this->drupalGet('admin/config/content/formats/manage/' . $format_id . '/disable');
    $this->drupalPost(NULL, array(), t('Disable'));

    // Verify that disabled text format no longer exists.
    $this->drupalGet('admin/config/content/formats/manage/' . $format_id);
    $this->assertResponse(404, 'Disabled text format no longer exists.');

    // Attempt to create a format of the same machine name as the disabled
    // format but with a different human readable name.
    $edit = array(
      'format' => $format_id,
      'name' => 'New format',
    );
    $this->drupalPost('admin/config/content/formats/add', $edit, t('Save configuration'));
    $this->assertText('The machine-readable name is already in use. It must be unique.');

    // Attempt to create a format of the same human readable name as the
    // disabled format but with a different machine name.
    $edit = array(
      'format' => 'new_format',
      'name' => $name,
    );
    $this->drupalPost('admin/config/content/formats/add', $edit, t('Save configuration'));
    $this->assertRaw(t('Text format names must be unique. A format named %name already exists.', array(
      '%name' => $name,
    )));
  }

  /**
   * Tests filter administration functionality.
   */
  function testFilterAdmin() {
    $first_filter = 'filter_autop';
    $second_filter = 'filter_url';

    $basic = 'basic_html';
    $restricted = 'restricted_html';
    $full = 'full_html';
    $plain = 'plain_text';

    // Check that the fallback format exists and cannot be disabled.
    $this->assertTrue($plain == filter_fallback_format(), 'The fallback format is set to plain text.');
    $this->drupalGet('admin/config/content/formats');
    $this->assertNoRaw('admin/config/content/formats/manage/' . $plain . '/disable', 'Disable link for the fallback format not found.');
    $this->drupalGet('admin/config/content/formats/manage/' . $plain . '/disable');
    $this->assertResponse(403, 'The fallback format cannot be disabled.');

    // Verify access permissions to Full HTML format.
    $this->assertTrue(filter_access(filter_format_load($full), $this->admin_user), 'Admin user may use Full HTML.');
    $this->assertFalse(filter_access(filter_format_load($full), $this->web_user), 'Web user may not use Full HTML.');

    // Add an additional tag.
    $edit = array();
    $edit['filters[filter_html][settings][allowed_html]'] = '<a> <em> <strong> <cite> <code> <ul> <ol> <li> <dl> <dt> <dd> <quote>';
    $this->drupalPost('admin/config/content/formats/manage/' . $restricted, $edit, t('Save configuration'));
    $this->assertUrl('admin/config/content/formats');
    $this->drupalGet('admin/config/content/formats/manage/' . $restricted);
    $this->assertFieldByName('filters[filter_html][settings][allowed_html]', $edit['filters[filter_html][settings][allowed_html]'], 'Allowed HTML tag added.');

    $this->assertTrue(cache('filter')->isEmpty(), 'Cache cleared.');

    $elements = $this->xpath('//select[@name=:first]/following::select[@name=:second]', array(
      ':first' => 'filters[' . $first_filter . '][weight]',
      ':second' => 'filters[' . $second_filter . '][weight]',
    ));
    $this->assertTrue(!empty($elements), 'Order confirmed in admin interface.');

    // Reorder filters.
    $edit = array();
    $edit['filters[' . $second_filter . '][weight]'] = 1;
    $edit['filters[' . $first_filter . '][weight]'] = 2;
    $this->drupalPost(NULL, $edit, t('Save configuration'));
    $this->assertUrl('admin/config/content/formats');
    $this->drupalGet('admin/config/content/formats/manage/' . $restricted);
    $this->assertFieldByName('filters[' . $second_filter . '][weight]', 1, 'Order saved successfully.');
    $this->assertFieldByName('filters[' . $first_filter . '][weight]', 2, 'Order saved successfully.');

    $elements = $this->xpath('//select[@name=:first]/following::select[@name=:second]', array(
      ':first' => 'filters[' . $second_filter . '][weight]',
      ':second' => 'filters[' . $first_filter . '][weight]',
    ));
    $this->assertTrue(!empty($elements), 'Reorder confirmed in admin interface.');

    $filter_format = entity_load('filter_format', $restricted);
    foreach ($filter_format->filters() as $filter_name => $filter) {
      if ($filter_name == $second_filter || $filter_name == $first_filter) {
        $filters[] = $filter_name;
      }
    }
    // Ensure that the second filter is now before the first filter.
    $this->assertEqual($filter_format->filters($second_filter)->weight + 1, $filter_format->filters($first_filter)->weight, 'Order confirmed in configuration.');

    // Add format.
    $edit = array();
    $edit['format'] = drupal_strtolower($this->randomName());
    $edit['name'] = $this->randomName();
    $edit['roles[' . DRUPAL_AUTHENTICATED_RID . ']'] = 1;
    $edit['filters[' . $second_filter . '][status]'] = TRUE;
    $edit['filters[' . $first_filter . '][status]'] = TRUE;
    $this->drupalPost('admin/config/content/formats/add', $edit, t('Save configuration'));
    $this->assertUrl('admin/config/content/formats');
    $this->assertRaw(t('Added text format %format.', array('%format' => $edit['name'])), 'New filter created.');

    filter_formats_reset();
    $format = filter_format_load($edit['format']);
    $this->assertNotNull($format, 'Format found in database.');
    $this->drupalGet('admin/config/content/formats/manage/' . $format->format);
    $this->assertFieldByName('roles[' . DRUPAL_AUTHENTICATED_RID . ']', '', 'Role found.');
    $this->assertFieldByName('filters[' . $second_filter . '][status]', '', 'Line break filter found.');
    $this->assertFieldByName('filters[' . $first_filter . '][status]', '', 'Url filter found.');

    // Disable new filter.
    $this->drupalPost('admin/config/content/formats/manage/' . $format->format . '/disable', array(), t('Disable'));
    $this->assertUrl('admin/config/content/formats');
    $this->assertRaw(t('Disabled text format %format.', array('%format' => $edit['name'])), 'Format successfully disabled.');

    // Allow authenticated users on full HTML.
    $format = filter_format_load($full);
    $edit = array();
    $edit['roles[' . DRUPAL_ANONYMOUS_RID . ']'] = 0;
    $edit['roles[' . DRUPAL_AUTHENTICATED_RID . ']'] = 1;
    $this->drupalPost('admin/config/content/formats/manage/' . $full, $edit, t('Save configuration'));
    $this->assertUrl('admin/config/content/formats');
    $this->assertRaw(t('The text format %format has been updated.', array('%format' => $format->name)), 'Full HTML format successfully updated.');

    // Switch user.
    $this->drupalLogin($this->web_user);

    $this->drupalGet('node/add/page');
    $this->assertRaw('<option value="' . $full . '">Full HTML</option>', 'Full HTML filter accessible.');

    // Use basic HTML and see if it removes tags that are not allowed.
    $body = '<em>' . $this->randomName() . '</em>';
    $extra_text = 'text';
    $text = $body . '<random>' . $extra_text . '</random>';

    $edit = array();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $edit["title"] = $this->randomName();
    $edit["body[$langcode][0][value]"] = $text;
    $edit["body[$langcode][0][format]"] = $basic;
    $this->drupalPost('node/add/page', $edit, t('Save'));
    $this->assertRaw(t('Basic page %title has been created.', array('%title' => $edit["title"])), 'Filtered node created.');

    $node = $this->drupalGetNodeByTitle($edit["title"]);
    $this->assertTrue($node, 'Node found in database.');

    $this->drupalGet('node/' . $node->id());
    $this->assertRaw($body . $extra_text, 'Filter removed invalid tag.');

    // Use plain text and see if it escapes all tags, whether allowed or not.
    // In order to test plain text, we have to enable the hidden variable for
    // "show_fallback_format", which displays plain text in the format list.
    \Drupal::config('filter.settings')
      ->set('always_show_fallback_choice', TRUE)
      ->save();
    $edit = array();
    $edit["body[$langcode][0][format]"] = $plain;
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save'));
    $this->drupalGet('node/' . $node->id());
    $this->assertText(check_plain($text), 'The "Plain text" text format escapes all HTML tags.');
    \Drupal::config('filter.settings')
      ->set('always_show_fallback_choice', FALSE)
      ->save();

    // Switch user.
    $this->drupalLogin($this->admin_user);

    // Clean up.
    // Allowed tags.
    $edit = array();
    $edit['filters[filter_html][settings][allowed_html]'] = '<a> <em> <strong> <cite> <code> <ul> <ol> <li> <dl> <dt> <dd>';
    $this->drupalPost('admin/config/content/formats/manage/' . $basic, $edit, t('Save configuration'));
    $this->assertUrl('admin/config/content/formats');
    $this->drupalGet('admin/config/content/formats/manage/' . $basic);
    $this->assertFieldByName('filters[filter_html][settings][allowed_html]', $edit['filters[filter_html][settings][allowed_html]'], 'Changes reverted.');

    // Full HTML.
    $edit = array();
    $edit['roles[' . DRUPAL_AUTHENTICATED_RID . ']'] = FALSE;
    $this->drupalPost('admin/config/content/formats/manage/' . $full, $edit, t('Save configuration'));
    $this->assertUrl('admin/config/content/formats');
    $this->assertRaw(t('The text format %format has been updated.', array('%format' => $format->name)), 'Full HTML format successfully reverted.');
    $this->drupalGet('admin/config/content/formats/manage/' . $full);
    $this->assertFieldByName('roles[' . DRUPAL_AUTHENTICATED_RID . ']', $edit['roles[' . DRUPAL_AUTHENTICATED_RID . ']'], 'Changes reverted.');

    // Filter order.
    $edit = array();
    $edit['filters[' . $second_filter . '][weight]'] = 2;
    $edit['filters[' . $first_filter . '][weight]'] = 1;
    $this->drupalPost('admin/config/content/formats/manage/' . $basic, $edit, t('Save configuration'));
    $this->assertUrl('admin/config/content/formats');
    $this->drupalGet('admin/config/content/formats/manage/' . $basic);
    $this->assertFieldByName('filters[' . $second_filter . '][weight]', $edit['filters[' . $second_filter . '][weight]'], 'Changes reverted.');
    $this->assertFieldByName('filters[' . $first_filter . '][weight]', $edit['filters[' . $first_filter . '][weight]'], 'Changes reverted.');
  }

  /**
   * Tests the URL filter settings form is properly validated.
   */
  function testUrlFilterAdmin() {
    // The form does not save with an invalid filter URL length.
    $edit = array(
      'filters[filter_url][settings][filter_url_length]' => $this->randomName(4),
    );
    $this->drupalPost('admin/config/content/formats/manage/basic_html', $edit, t('Save configuration'));
    $this->assertNoRaw(t('The text format %format has been updated.', array('%format' => 'Basic HTML')));
  }
}
