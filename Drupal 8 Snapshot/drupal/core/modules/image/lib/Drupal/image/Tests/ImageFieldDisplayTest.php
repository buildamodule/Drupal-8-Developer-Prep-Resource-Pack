<?php

/**
 * @file
 * Definition of Drupal\image\Tests\ImageFieldDisplayTest.
 */

namespace Drupal\image\Tests;

use Drupal\Core\Language\Language;

/**
 * Test class to check that formatters and display settings are working.
 */
class ImageFieldDisplayTest extends ImageFieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('field_ui');

  public static function getInfo() {
    return array(
      'name' => 'Image field display tests',
      'description' => 'Test the display of image fields.',
      'group' => 'Image',
    );
  }

  /**
   * Test image formatters on node display for public files.
   */
  function testImageFieldFormattersPublic() {
    $this->_testImageFieldFormatters('public');
  }

  /**
   * Test image formatters on node display for private files.
   */
  function testImageFieldFormattersPrivate() {
    // Remove access content permission from anonymous users.
    user_role_change_permissions(DRUPAL_ANONYMOUS_RID, array('access content' => FALSE));
    $this->_testImageFieldFormatters('private');
  }

  /**
   * Test image formatters on node display.
   */
  function _testImageFieldFormatters($scheme) {
    $field_name = strtolower($this->randomName());
    $this->createImageField($field_name, 'article', array('uri_scheme' => $scheme));

    // Create a new node with an image attached.
    $test_image = current($this->drupalGetTestFiles('image'));
    $nid = $this->uploadNodeImage($test_image, $field_name, 'article');
    $node = node_load($nid, TRUE);

    // Test that the default formatter is being used.
    $image_uri = file_load($node->{$field_name}[Language::LANGCODE_NOT_SPECIFIED][0]['target_id'])->getFileUri();
    $image_info = array(
      'uri' => $image_uri,
      'width' => 40,
      'height' => 20,
    );
    $default_output = theme('image', $image_info);
    $this->assertRaw($default_output, 'Default formatter displaying correctly on full node view.');

    // Test the image linked to file formatter.
    $display_options = array(
      'type' => 'image',
      'settings' => array('image_link' => 'file'),
    );
    $display = entity_get_display('node', $node->type, 'default');
    $display->setComponent($field_name, $display_options)
      ->save();

    $default_output = l(theme('image', $image_info), file_create_url($image_uri), array('html' => TRUE));
    $this->drupalGet('node/' . $nid);
    $this->assertRaw($default_output, 'Image linked to file formatter displaying correctly on full node view.');
    // Verify that the image can be downloaded.
    $this->assertEqual(file_get_contents($test_image->uri), $this->drupalGet(file_create_url($image_uri)), 'File was downloaded successfully.');
    if ($scheme == 'private') {
      // Only verify HTTP headers when using private scheme and the headers are
      // sent by Drupal.
      $this->assertEqual($this->drupalGetHeader('Content-Type'), 'image/png', 'Content-Type header was sent.');
      $this->assertTrue(strstr($this->drupalGetHeader('Cache-Control'),'private') !== FALSE, 'Cache-Control header was sent.');

      // Log out and try to access the file.
      $this->drupalLogout();
      $this->drupalGet(file_create_url($image_uri));
      $this->assertResponse('403', 'Access denied to original image as anonymous user.');

      // Log in again.
      $this->drupalLogin($this->admin_user);
    }

    // Test the image linked to content formatter.
    $display_options['settings']['image_link'] = 'content';
    $display->setComponent($field_name, $display_options)
      ->save();
    $default_output = l(theme('image', $image_info), 'node/' . $nid, array('html' => TRUE, 'attributes' => array('class' => 'active')));
    $this->drupalGet('node/' . $nid);
    $this->assertRaw($default_output, 'Image linked to content formatter displaying correctly on full node view.');

    // Test the image style 'thumbnail' formatter.
    $display_options['settings']['image_link'] = '';
    $display_options['settings']['image_style'] = 'thumbnail';
    $display->setComponent($field_name, $display_options)
      ->save();

    // Ensure the derivative image is generated so we do not have to deal with
    // image style callback paths.
    $this->drupalGet(entity_load('image_style', 'thumbnail')->buildUrl($image_uri));
    $image_info['uri'] = $image_uri;
    $image_info['width'] = 100;
    $image_info['height'] = 50;
    $image_info['style_name'] = 'thumbnail';
    $default_output = theme('image_style', $image_info);
    $this->drupalGet('node/' . $nid);
    $this->assertRaw($default_output, 'Image style thumbnail formatter displaying correctly on full node view.');

    if ($scheme == 'private') {
      // Log out and try to access the file.
      $this->drupalLogout();
      $this->drupalGet(entity_load('image_style', 'thumbnail')->buildUrl($image_uri));
      $this->assertResponse('403', 'Access denied to image style thumbnail as anonymous user.');
    }
  }

  /**
   * Tests for image field settings.
   */
  function testImageFieldSettings() {
    $test_image = current($this->drupalGetTestFiles('image'));
    list(, $test_image_extension) = explode('.', $test_image->filename);
    $field_name = strtolower($this->randomName());
    $instance_settings = array(
      'alt_field' => 1,
      'file_extensions' => $test_image_extension,
      'max_filesize' => '50 KB',
      'max_resolution' => '100x100',
      'min_resolution' => '10x10',
      'title_field' => 1,
      'description' => '[site:name]_description',
    );
    $widget_settings = array(
      'preview_image_style' => 'medium',
    );
    $instance = $this->createImageField($field_name, 'article', array(), $instance_settings, $widget_settings);

    $this->drupalGet('node/add/article');
    $this->assertText(t('Files must be less than 50 KB.'), 'Image widget max file size is displayed on article form.');
    $this->assertText(t('Allowed file types: ' . $test_image_extension . '.'), 'Image widget allowed file types displayed on article form.');
    $this->assertText(t('Images must be between 10x10 and 100x100 pixels.'), 'Image widget allowed resolution displayed on article form.');

    // We have to create the article first and then edit it because the alt
    // and title fields do not display until the image has been attached.
    $nid = $this->uploadNodeImage($test_image, $field_name, 'article');
    $this->drupalGet('node/' . $nid . '/edit');
    $this->assertFieldByName($field_name . '[' . Language::LANGCODE_NOT_SPECIFIED . '][0][alt]', '', 'Alt field displayed on article form.');
    $this->assertFieldByName($field_name . '[' . Language::LANGCODE_NOT_SPECIFIED . '][0][title]', '', 'Title field displayed on article form.');
    // Verify that the attached image is being previewed using the 'medium'
    // style.
    $node = node_load($nid, TRUE);
    $image_info = array(
      'uri' => file_load($node->{$field_name}[Language::LANGCODE_NOT_SPECIFIED][0]['target_id'])->getFileUri(),
      'width' => 220,
      'height' => 110,
      'style_name' => 'medium',
    );
    $default_output = theme('image_style', $image_info);
    $this->assertRaw($default_output, "Preview image is displayed using 'medium' style.");

    // Add alt/title fields to the image and verify that they are displayed.
    $image_info = array(
      'uri' => file_load($node->{$field_name}[Language::LANGCODE_NOT_SPECIFIED][0]['target_id'])->getFileUri(),
      'alt' => $this->randomName(),
      'title' => $this->randomName(),
      'width' => 40,
      'height' => 20,
    );
    $edit = array(
      $field_name . '[' . Language::LANGCODE_NOT_SPECIFIED . '][0][alt]' => $image_info['alt'],
      $field_name . '[' . Language::LANGCODE_NOT_SPECIFIED . '][0][title]' => $image_info['title'],
    );
    $this->drupalPost('node/' . $nid . '/edit', $edit, t('Save and keep published'));
    $default_output = theme('image', $image_info);
    $this->assertRaw($default_output, 'Image displayed using user supplied alt and title attributes.');

    // Verify that alt/title longer than allowed results in a validation error.
    $test_size = 2000;
    $edit = array(
      $field_name . '[' . Language::LANGCODE_NOT_SPECIFIED . '][0][alt]' => $this->randomName($test_size),
      $field_name . '[' . Language::LANGCODE_NOT_SPECIFIED . '][0][title]' => $this->randomName($test_size),
    );
    $this->drupalPost('node/' . $nid . '/edit', $edit, t('Save and keep published'));
    $schema = $instance->getField()->getSchema();
    $this->assertRaw(t('Alternate text cannot be longer than %max characters but is currently %length characters long.', array(
      '%max' => $schema['columns']['alt']['length'],
      '%length' => $test_size,
    )));
    $this->assertRaw(t('Title cannot be longer than %max characters but is currently %length characters long.', array(
      '%max' => $schema['columns']['title']['length'],
      '%length' => $test_size,
    )));
  }

  /**
   * Test use of a default image with an image field.
   */
  function testImageFieldDefaultImage() {
    // Create a new image field.
    $field_name = strtolower($this->randomName());
    $this->createImageField($field_name, 'article');

    // Create a new node, with no images and verify that no images are
    // displayed.
    $node = $this->drupalCreateNode(array('type' => 'article'));
    $this->drupalGet('node/' . $node->id());
    // Verify that no image is displayed on the page by checking for the class
    // that would be used on the image field.
    $this->assertNoPattern('<div class="(.*?)field-name-' . strtr($field_name, '_', '-') . '(.*?)">', 'No image displayed when no image is attached and no default image specified.');

    // Add a default image to the public imagefield instance.
    $images = $this->drupalGetTestFiles('image');
    $edit = array(
      'files[field_settings_default_image]' => drupal_realpath($images[0]->uri),
    );
    $this->drupalPost("admin/structure/types/manage/article/fields/node.article.$field_name/field", $edit, t('Save field settings'));
    // Clear field info cache so the new default image is detected.
    field_info_cache_clear();
    $field = field_info_field($field_name);
    $image = file_load($field['settings']['default_image']);
    $this->assertTrue($image->isPermanent(), 'The default image status is permanent.');
    $default_output = theme('image', array('uri' => $image->getFileUri()));
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw($default_output, 'Default image displayed when no user supplied image is present.');

    // Create a node with an image attached and ensure that the default image
    // is not displayed.
    $nid = $this->uploadNodeImage($images[1], $field_name, 'article');
    $node = node_load($nid, TRUE);
    $image_info = array(
      'uri' => file_load($node->{$field_name}[Language::LANGCODE_NOT_SPECIFIED][0]['target_id'])->getFileUri(),
      'width' => 40,
      'height' => 20,
    );
    $image_output = theme('image', $image_info);
    $this->drupalGet('node/' . $nid);
    $this->assertNoRaw($default_output, 'Default image is not displayed when user supplied image is present.');
    $this->assertRaw($image_output, 'User supplied image is displayed.');

    // Remove default image from the field and make sure it is no longer used.
    $edit = array(
      'field[settings][default_image][fids]' => 0,
    );
    $this->drupalPost("admin/structure/types/manage/article/fields/node.article.$field_name/field", $edit, t('Save field settings'));
    // Clear field info cache so the new default image is detected.
    field_info_cache_clear();
    $field = field_info_field($field_name);
    $this->assertFalse($field['settings']['default_image'], 'Default image removed from field.');
    // Create an image field that uses the private:// scheme and test that the
    // default image works as expected.
    $private_field_name = strtolower($this->randomName());
    $this->createImageField($private_field_name, 'article', array('uri_scheme' => 'private'));
    // Add a default image to the new field.
    $edit = array(
      'files[field_settings_default_image]' => drupal_realpath($images[1]->uri),
    );
    $this->drupalPost('admin/structure/types/manage/article/fields/node.article.' . $private_field_name . '/field', $edit, t('Save field settings'));
    // Clear field info cache so the new default image is detected.
    field_info_cache_clear();

    $private_field = field_info_field($private_field_name);
    $image = file_load($private_field['settings']['default_image']);
    $this->assertEqual('private', file_uri_scheme($image->getFileUri()), 'Default image uses private:// scheme.');
    $this->assertTrue($image->isPermanent(), 'The default image status is permanent.');
    // Create a new node with no image attached and ensure that default private
    // image is displayed.
    $node = $this->drupalCreateNode(array('type' => 'article'));
    $default_output = theme('image', array('uri' => $image->getFileUri()));
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw($default_output, 'Default private image displayed when no user supplied image is present.');
  }
}
