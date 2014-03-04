<?php

/**
 * @file
 * Definition of Drupal\file\Tests\FileTokenReplaceTest.
 */

namespace Drupal\file\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests the file token replacement in strings.
 */
class FileTokenReplaceTest extends FileFieldTestBase {
  public static function getInfo() {
    return array(
      'name' => 'File token replacement',
      'description' => 'Generates text using placeholders for dummy content to check file token replacement.',
      'group' => 'File',
    );
  }

  /**
   * Creates a file, then tests the tokens generated from it.
   */
  function testFileTokenReplacement() {
    $token_service = \Drupal::token();
    $language_interface = language(Language::TYPE_INTERFACE);
    $url_options = array(
      'absolute' => TRUE,
      'language' => $language_interface,
    );

    // Create file field.
    $type_name = 'article';
    $field_name = 'field_' . strtolower($this->randomName());
    $this->createFileField($field_name, $type_name);

    $test_file = $this->getTestFile('text');
    // Coping a file to test uploads with non-latin filenames.
    $filename = drupal_dirname($test_file->getFileUri()) . '/текстовый файл.txt';
    $test_file = file_copy($test_file, $filename);

    // Create a new node with the uploaded file.
    $nid = $this->uploadNodeFile($test_file, $field_name, $type_name);

    // Load the node and the file.
    $node = node_load($nid, TRUE);
    $file = file_load($node->{$field_name}[Language::LANGCODE_NOT_SPECIFIED][0]['target_id']);

    // Generate and test sanitized tokens.
    $tests = array();
    $tests['[file:fid]'] = $file->id();
    $tests['[file:name]'] = check_plain($file->getFilename());
    $tests['[file:path]'] = check_plain($file->getFileUri());
    $tests['[file:mime]'] = check_plain($file->getMimeType());
    $tests['[file:size]'] = format_size($file->getSize());
    $tests['[file:url]'] = check_plain(file_create_url($file->getFileUri()));
    $tests['[file:timestamp]'] = format_date($file->getChangedTime(), 'medium', '', NULL, $language_interface->id);
    $tests['[file:timestamp:short]'] = format_date($file->getChangedTime(), 'short', '', NULL, $language_interface->id);
    $tests['[file:owner]'] = check_plain(user_format_name($this->admin_user));
    $tests['[file:owner:uid]'] = $file->getOwner()->id();

    // Test to make sure that we generated something for each token.
    $this->assertFalse(in_array(0, array_map('strlen', $tests)), 'No empty tokens generated.');

    foreach ($tests as $input => $expected) {
      $output = $token_service->replace($input, array('file' => $file), array('langcode' => $language_interface->id));
      $this->assertEqual($output, $expected, format_string('Sanitized file token %token replaced.', array('%token' => $input)));
    }

    // Generate and test unsanitized tokens.
    $tests['[file:name]'] = $file->getFilename();
    $tests['[file:path]'] = $file->getFileUri();
    $tests['[file:mime]'] = $file->getMimeType();
    $tests['[file:size]'] = format_size($file->getSize());

    foreach ($tests as $input => $expected) {
      $output = $token_service->replace($input, array('file' => $file), array('langcode' => $language_interface->id, 'sanitize' => FALSE));
      $this->assertEqual($output, $expected, format_string('Unsanitized file token %token replaced.', array('%token' => $input)));
    }
  }
}
