<?php

/**
 * @file
 * Definition of \Drupal\editor\Tests\EditorLoadingTest.
 */

namespace Drupal\editor\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests loading of text editors.
 */
class EditorLoadingTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('filter', 'editor', 'editor_test', 'node');

  public static function getInfo() {
    return array(
      'name' => 'Text editor loading',
      'description' => 'Tests loading of text editors.',
      'group' => 'Text Editor',
    );
  }

  function setUp() {
    parent::setUp();

    // Add text formats.
    $filtered_html_format = entity_create('filter_format', array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => array(),
    ));
    $filtered_html_format->save();
    $full_html_format = entity_create('filter_format', array(
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => array(),
    ));
    $full_html_format->save();

    // Create node type.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article',
    ));

    // Create 3 users, each with access to different text formats:
    //   - "untrusted": plain_text
    //   - "normal": plain_text, filtered_html
    //   - "privileged": plain_text, filtered_html, full_html
    $this->untrusted_user = $this->drupalCreateUser(array('create article content', 'edit any article content'));
    $this->normal_user = $this->drupalCreateUser(array('create article content', 'edit any article content', 'use text format filtered_html'));
    $this->privileged_user = $this->drupalCreateUser(array('create article content', 'edit any article content', 'use text format filtered_html', 'use text format full_html'));
  }

  /**
   * Tests loading of text editors.
   */
  function testLoading() {
    // Only associate a text editor with the "Full HTML" text format.
    $editor = entity_create('editor', array(
      'format' => 'full_html',
      'editor' => 'unicorn',
    ));
    $editor->save();

    // The normal user:
    // - has access to 2 text formats;
    // - doesn't have access to the full_html text format, so: no text editor.
    $this->drupalLogin($this->normal_user);
    $this->drupalGet('node/add/article');
    list($settings, $editor_settings_present, $editor_js_present, $body, $format_selector) = $this->getThingsToCheck();
    $this->assertFalse($editor_settings_present, 'No Text Editor module settings.');
    $this->assertFalse($editor_js_present, 'No Text Editor JavaScript.');
    $this->assertTrue(count($body) === 1, 'A body field exists.');
    $this->assertTrue(count($format_selector) === 0, 'No text format selector exists on the page because the user only has access to a single format.');
    $this->drupalLogout($this->normal_user);

    // The normal user:
    // - has access to 2 text formats (and the fallback format);
    // - does have access to the full_html text format, so: Unicorn text editor.
    $this->drupalLogin($this->privileged_user);
    $this->drupalGet('node/add/article');
    list($settings, $editor_settings_present, $editor_js_present, $body, $format_selector) = $this->getThingsToCheck();
    $expected = array('formats' => array('full_html' => array(
      'format' => 'full_html',
      'editor' => 'unicorn',
      'editorSettings' => array('ponyModeEnabled' => TRUE),
    )));
    $this->assertTrue($editor_settings_present, "Text Editor module's JavaScript settings are on the page.");
    $this->assertIdentical($expected, $settings['editor'], "Text Editor module's JavaScript settings on the page are correct.");
    $this->assertTrue($editor_js_present, 'Text Editor JavaScript is present.');
    $this->assertTrue(count($body) === 1, 'A body field exists.');
    $this->assertTrue(count($format_selector) === 1, 'A single text format selector exists on the page.');
    $specific_format_selector = $this->xpath('//select[contains(@class, "filter-list") and contains(@class, "editor") and @data-editor-for="edit-body-und-0-value"]');
    $this->assertTrue(count($specific_format_selector) === 1, 'A single text format selector exists on the page and has the "editor" class and a "data-editor-for" attribute with the correct value.');
    $this->drupalLogout($this->privileged_user);

    // Also associate a text editor with the "Plain Text" text format.
    $editor = entity_create('editor', array(
      'format' => 'plain_text',
      'editor' => 'unicorn',
    ));
    $editor->save();

    // The untrusted user:
    // - has access to 1 text format (plain_text);
    // - has access to the plain_text text format, so: Unicorn text editor.
    $this->drupalLogin($this->untrusted_user);
    $this->drupalGet('node/add/article');
    list($settings, $editor_settings_present, $editor_js_present, $body, $format_selector) = $this->getThingsToCheck();
    $expected = array('formats' => array('plain_text' => array(
      'format' => 'plain_text',
      'editor' => 'unicorn',
      'editorSettings' => array('ponyModeEnabled' => TRUE),
    )));
    $this->assertTrue($editor_settings_present, "Text Editor module's JavaScript settings are on the page.");
    $this->assertIdentical($expected, $settings['editor'], "Text Editor module's JavaScript settings on the page are correct.");
    $this->assertTrue($editor_js_present, 'Text Editor JavaScript is present.');
    $this->assertTrue(count($body) === 1, 'A body field exists.');
    $this->assertTrue(count($format_selector) === 0, 'No text format selector exists on the page.');
    $hidden_input = $this->xpath('//input[@type="hidden" and @value="plain_text" and contains(@class, "editor") and @data-editor-for="edit-body-und-0-value"]');
    $this->assertTrue(count($hidden_input) === 1, 'A single text format hidden input exists on the page and has the "editor" class and a "data-editor-for" attribute with the correct value.');

    // Create an "article" node that users the full_html text format, then try
    // to let the untrusted user edit it.
    $this->drupalCreateNode(array(
      'type' => 'article',
      'body' => array(
        array('value' => $this->randomName(32), 'format' => 'full_html')
      ),
    ));

    // The untrusted user tries to edit content that is written in a text format
    // that (s)he is not allowed to use.
    $this->drupalGet('node/1/edit');
    list($settings, $editor_settings_present, $editor_js_present, $body, $format_selector) = $this->getThingsToCheck();
    $this->assertFalse($editor_settings_present, 'No Text Editor module settings.');
    $this->assertFalse($editor_js_present, 'No Text Editor JavaScript.');
    $this->assertTrue(count($body) === 1, 'A body field exists.');
    $this->assertFieldByXPath('//textarea[@id="edit-body-und-0-value" and @disabled="disabled"]', t('This field has been disabled because you do not have sufficient permissions to edit it.'), 'Text format access denied message found.');
    $this->assertTrue(count($format_selector) === 0, 'No text format selector exists on the page.');
    $hidden_input = $this->xpath('//input[@type="hidden" and contains(@class, "editor")]');
    $this->assertTrue(count($hidden_input) === 0, 'A single text format hidden input does not exist on the page.');
  }

  protected function getThingsToCheck() {
    $settings = $this->drupalGetSettings();
    return array(
      // JavaScript settings.
      $settings,
      // Editor.module's JS settings present.
      isset($settings['editor']),
      // Editor.module's JS present.
      isset($settings['ajaxPageState']['js']['core/modules/editor/js/editor.js']),
      // Body field.
      $this->xpath('//textarea[@id="edit-body-und-0-value"]'),
      // Format selector.
      $this->xpath('//select[contains(@class, "filter-list")]'),
    );
  }
}
