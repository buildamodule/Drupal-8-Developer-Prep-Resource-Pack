<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Common\XssUnitTest.
 */

namespace Drupal\system\Tests\Common;

use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests for filter_xss() and check_url().
 */
class XssUnitTest extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('filter');

  public static function getInfo() {
    return array(
      'name' => 'String filtering tests',
      'description' => 'Confirm that filter_xss() and check_url() work correctly, including invalid multi-byte sequences.',
      'group' => 'Common',
    );
  }

  protected function setUp() {
    parent::setUp();
    config_install_default_config('module', 'system');
  }

  /**
   * Tests t() functionality.
   */
  function testT() {
    $text = t('Simple text');
    $this->assertEqual($text, 'Simple text', 't leaves simple text alone.');
    $text = t('Escaped text: @value', array('@value' => '<script>'));
    $this->assertEqual($text, 'Escaped text: &lt;script&gt;', 't replaces and escapes string.');
    $text = t('Placeholder text: %value', array('%value' => '<script>'));
    $this->assertEqual($text, 'Placeholder text: <em class="placeholder">&lt;script&gt;</em>', 't replaces, escapes and themes string.');
    $text = t('Verbatim text: !value', array('!value' => '<script>'));
    $this->assertEqual($text, 'Verbatim text: <script>', 't replaces verbatim string as-is.');
  }

  /**
   * Checks that harmful protocols are stripped.
   */
  function testBadProtocolStripping() {
    // Ensure that check_url() strips out harmful protocols, and encodes for
    // HTML. Ensure drupal_strip_dangerous_protocols() can be used to return a
    // plain-text string stripped of harmful protocols.
    $url = 'javascript:http://www.example.com/?x=1&y=2';
    $expected_plain = 'http://www.example.com/?x=1&y=2';
    $expected_html = 'http://www.example.com/?x=1&amp;y=2';
    $this->assertIdentical(check_url($url), $expected_html, 'check_url() filters a URL and encodes it for HTML.');
    $this->assertIdentical(drupal_strip_dangerous_protocols($url), $expected_plain, 'drupal_strip_dangerous_protocols() filters a URL and returns plain text.');
  }
}
