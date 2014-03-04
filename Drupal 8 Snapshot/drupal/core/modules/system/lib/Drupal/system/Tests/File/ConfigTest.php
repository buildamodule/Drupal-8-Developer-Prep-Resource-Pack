<?php

/**
 * @file
 * Definition of Drupal\system\Tests\File\ConfigTest.
 */

namespace Drupal\system\Tests\File;

/**
 * File system configuration related tests.
 */
class ConfigTest extends FileTestBase {

  public static function getInfo() {
    return array(
      'name' => 'File system configuration test',
      'description' => 'Tests file system configuration operations',
      'group' => 'File API',
    );
  }

  function setUp(){
    parent::setUp();
    $this->web_user = $this->drupalCreateUser(array('administer site configuration'));
    $this->drupalLogin($this->web_user);
  }

  /**
   * Tests file configuration page.
   */
  function testFileConfigurationPage() {
    $this->drupalGet('admin/config/media/file-system');

    // Set the file paths to non-default values.
    // The respective directories are created automatically
    // upon form submission.
    $file_path = $this->public_files_directory;
    $fields = array(
      'file_public_path' => $file_path . '/file_config_page_test/public',
      'file_private_path' => $file_path . '/file_config_page_test/private',
      'file_temporary_path' => $file_path . '/file_config_page_test/temporary',
      'file_default_scheme' => 'private',
    );

    // Check that all fields are present.
    foreach ($fields as $field => $path) {
      $this->assertFieldByName($field);
    }

    $this->drupalPost(NULL, $fields, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'));
    foreach ($fields as $field => $value) {
      $this->assertFieldByName($field, $value);
    }
  }
}
