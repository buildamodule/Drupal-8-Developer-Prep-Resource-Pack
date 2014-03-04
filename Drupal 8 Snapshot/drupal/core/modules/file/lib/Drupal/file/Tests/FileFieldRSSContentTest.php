<?php

/**
 * @file
 * Definition of Drupal\file\Tests\FileFieldDisplayTest.
 */

namespace Drupal\file\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests that formatters are working properly.
 */
class FileFieldRSSContentTest extends FileFieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'views');

  public static function getInfo() {
    return array(
      'name' => 'File field RSS content',
      'description' => 'Ensure that files added to nodes appear correctly in RSS feeds.',
      'group' => 'File',
    );
  }

  /**
   * Tests RSS enclosure formatter display for RSS feeds.
   */
  function testFileFieldRSSContent() {
    $field_name = strtolower($this->randomName());
    $type_name = 'article';
    $field_settings = array(
      'display_field' => '1',
      'display_default' => '1',
    );
    $instance_settings = array(
      'description_field' => '1',
    );
    $widget_settings = array();
    $this->createFileField($field_name, $type_name, $field_settings, $instance_settings, $widget_settings);

    // RSS display must be added manually.
    $this->drupalGet("admin/structure/types/manage/$type_name/display");
    $edit = array(
      "display_modes_custom[rss]" => '1',
    );
    $this->drupalPost(NULL, $edit, t('Save'));

    // Change the format to 'RSS enclosure'.
    $this->drupalGet("admin/structure/types/manage/$type_name/display/rss");
    $edit = array("fields[$field_name][type]" => 'file_rss_enclosure');
    $this->drupalPost(NULL, $edit, t('Save'));

    // Create a new node with a file field set. Promote to frontpage
    // needs to be set so this node will appear in the RSS feed.
    $node = $this->drupalCreateNode(array('type' => $type_name, 'promote' => 1));
    $test_file = $this->getTestFile('text');

    // Create a new node with the uploaded file.
    $nid = $this->uploadNodeFile($test_file, $field_name, $node->id());

    // Get the uploaded file from the node.
    $node = node_load($nid, TRUE);
    $node_file = file_load($node->{$field_name}[Language::LANGCODE_NOT_SPECIFIED][0]['target_id']);

    // Check that the RSS enclosure appears in the RSS feed.
    $this->drupalGet('rss.xml');
    $uploaded_filename = str_replace('public://', '', $node_file->getFileUri());
    $test_element = array(
      'key' => 'enclosure',
      'value' => "",
      'attributes' => array(
        'url' => url("$this->public_files_directory/$uploaded_filename", array('absolute' => TRUE)),
        'length' => $node_file->getSize(),
        'type' => $node_file->getMimeType()
      ),
    );
    $this->assertRaw(format_xml_elements(array($test_element)), 'File field RSS enclosure is displayed when viewing the RSS feed.');
  }
}
