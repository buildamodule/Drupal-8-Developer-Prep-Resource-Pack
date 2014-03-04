<?php

/**
 * @file
 * Contains Drupal\filter\Tests\FilterHtmlImageSecureTest.
 */

namespace Drupal\filter\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests restriction of IMG tags in HTML input.
 */
class FilterHtmlImageSecureTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('filter', 'node', 'comment');

  public static function getInfo() {
    return array(
      'name' => 'Local image input filter',
      'description' => 'Tests restriction of IMG tags in HTML input.',
      'group' => 'Filter',
    );
  }

  function setUp() {
    parent::setUp();

    // Setup Filtered HTML text format.
    $filtered_html_format = entity_create('filter_format', array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'filters' => array(
        'filter_html' => array(
          'status' => 1,
          'settings' => array(
            'allowed_html' => '<img> <a>',
          ),
        ),
        'filter_autop' => array(
          'status' => 1,
        ),
        'filter_html_image_secure' => array(
          'status' => 1,
        ),
      ),
    ));
    $filtered_html_format->save();

    // Setup users.
    $this->checkPermissions(array(), TRUE);
    $this->web_user = $this->drupalCreateUser(array(
      'access content',
      'access comments',
      'post comments',
      'skip comment approval',
      filter_permission_name($filtered_html_format),
    ));
    $this->drupalLogin($this->web_user);

    // Setup a node to comment and test on.
    $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
    $this->node = $this->drupalCreateNode();
  }

  /**
   * Tests removal of images having a non-local source.
   */
  function testImageSource() {
    global $base_url;

    $public_files_path = variable_get('file_public_path', conf_path() . '/files');

    $http_base_url = preg_replace('/^https?/', 'http', $base_url);
    $https_base_url = preg_replace('/^https?/', 'https', $base_url);
    $files_path = base_path() . $public_files_path;
    $csrf_path = $public_files_path . '/' . implode('/', array_fill(0, substr_count($public_files_path, '/') + 1, '..'));

    $druplicon = 'core/misc/druplicon.png';
    $red_x_image = base_path() . 'core/misc/message-16-error.png';
    $alt_text = t('Image removed.');
    $title_text = t('This image has been removed. For security reasons, only images from the local domain are allowed.');

    // Put a test image in the files directory.
    $test_images = $this->drupalGetTestFiles('image');
    $test_image = $test_images[0]->filename;

    // Create a list of test image sources.
    // The keys become the value of the IMG 'src' attribute, the values are the
    // expected filter conversions.
    $images = array(
      $http_base_url . '/' . $druplicon => base_path() . $druplicon,
      $https_base_url . '/' . $druplicon => base_path() . $druplicon,
      base_path() . $druplicon => base_path() . $druplicon,
      $files_path . '/' . $test_image => $files_path . '/' . $test_image,
      $http_base_url . '/' . $public_files_path . '/' . $test_image => $files_path . '/' . $test_image,
      $https_base_url . '/' . $public_files_path . '/' . $test_image => $files_path . '/' . $test_image,
      $files_path . '/example.png' => $red_x_image,
      'http://example.com/' . $druplicon => $red_x_image,
      'https://example.com/' . $druplicon => $red_x_image,
      'javascript:druplicon.png' => $red_x_image,
      $csrf_path . '/logout' => $red_x_image,
    );
    $comment = array();
    foreach ($images as $image => $converted) {
      // Output the image source as plain text for debugging.
      $comment[] = $image . ':';
      // Hash the image source in a custom test attribute, because it might
      // contain characters that confuse XPath.
      $comment[] = '<img src="' . $image . '" testattribute="' . hash('sha256', $image) . '" />';
    }
    $edit = array(
      'comment_body[und][0][value]' => implode("\n", $comment),
    );
    $this->drupalPost('node/' . $this->node->id(), $edit, t('Save'));
    foreach ($images as $image => $converted) {
      $found = FALSE;
      foreach ($this->xpath('//img[@testattribute="' . hash('sha256', $image) . '"]') as $element) {
        $found = TRUE;
        if ($converted == $red_x_image) {
          $this->assertEqual((string) $element['src'], $red_x_image);
          $this->assertEqual((string) $element['alt'], $alt_text);
          $this->assertEqual((string) $element['title'], $title_text);
        }
        else {
          $this->assertEqual((string) $element['src'], $converted);
        }
      }
      $this->assertTrue($found, format_string('@image was found.', array('@image' => $image)));
    }
  }
}
