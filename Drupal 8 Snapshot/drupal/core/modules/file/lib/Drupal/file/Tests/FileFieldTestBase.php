<?php

/**
 * @file
 * Definition of Drupal\file\Tests\FileFieldTestBase.
 */

namespace Drupal\file\Tests;

use Drupal\Core\Language\Language;
use Drupal\file\FileInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Provides methods specifically for testing File module's field handling.
 */
abstract class FileFieldTestBase extends WebTestBase {

  /**
  * Modules to enable.
  *
  * @var array
  */
  public static $modules = array('file', 'file_module_test', 'field_ui');

  protected $admin_user;

  function setUp() {
    parent::setUp();
    $this->admin_user = $this->drupalCreateUser(array('access content', 'access administration pages', 'administer site configuration', 'administer users', 'administer permissions', 'administer content types', 'administer node fields', 'administer node display', 'administer nodes', 'bypass node access'));
    $this->drupalLogin($this->admin_user);
    $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
  }

  /**
   * Retrieves a sample file of the specified type.
   */
  function getTestFile($type_name, $size = NULL) {
    // Get a file to upload.
    $file = current($this->drupalGetTestFiles($type_name, $size));

    // Add a filesize property to files as would be read by file_load().
    $file->filesize = filesize($file->uri);

    return entity_create('file', (array) $file);
  }

  /**
   * Retrieves the fid of the last inserted file.
   */
  function getLastFileId() {
    return (int) db_query('SELECT MAX(fid) FROM {file_managed}')->fetchField();
  }

  /**
   * Creates a new file field.
   *
   * @param $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param $type_name
   *   The node type that this field will be added to.
   * @param $field_settings
   *   A list of field settings that will be added to the defaults.
   * @param $instance_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   */
  function createFileField($name, $type_name, $field_settings = array(), $instance_settings = array(), $widget_settings = array()) {
    $field_definition = array(
      'field_name' => $name,
      'type' => 'file',
      'settings' => array(),
      'cardinality' => !empty($field_settings['cardinality']) ? $field_settings['cardinality'] : 1,
    );
    $field_definition['settings'] = array_merge($field_definition['settings'], $field_settings);
    $field = entity_create('field_entity', $field_definition);
    $field->save();

    $this->attachFileField($name, 'node', $type_name, $instance_settings, $widget_settings);
    return $field;
  }

  /**
   * Attaches a file field to an entity.
   *
   * @param $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param $entity_type
   *   The entity type this field will be added to.
   * @param $bundle
   *   The bundle this field will be added to.
   * @param $field_settings
   *   A list of field settings that will be added to the defaults.
   * @param $instance_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   */
  function attachFileField($name, $entity_type, $bundle, $instance_settings = array(), $widget_settings = array()) {
    $instance = array(
      'field_name' => $name,
      'label' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'required' => !empty($instance_settings['required']),
      'settings' => array(),
    );
    $instance['settings'] = array_merge($instance['settings'], $instance_settings);
    entity_create('field_instance', $instance)->save();

    entity_get_form_display($entity_type, $bundle, 'default')
      ->setComponent($name, array(
        'type' => 'file_generic',
        'settings' => $widget_settings,
      ))
      ->save();
  }

  /**
   * Updates an existing file field with new settings.
   */
  function updateFileField($name, $type_name, $instance_settings = array(), $widget_settings = array()) {
    $instance = field_info_instance('node', $name, $type_name);
    $instance['settings'] = array_merge($instance['settings'], $instance_settings);

    $instance->save();

    entity_get_form_display($instance['entity_type'], $instance['bundle'], 'default')
      ->setComponent($instance['field_name'], array(
        'settings' => $widget_settings,
      ))
      ->save();
  }

  /**
   * Uploads a file to a node.
   */
  function uploadNodeFile($file, $field_name, $nid_or_type, $new_revision = TRUE, $extras = array()) {
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $edit = array(
      "title" => $this->randomName(),
      'revision' => (string) (int) $new_revision,
    );

    if (is_numeric($nid_or_type)) {
      $nid = $nid_or_type;
    }
    else {
      // Add a new node.
      $extras['type'] = $nid_or_type;
      $node = $this->drupalCreateNode($extras);
      $nid = $node->id();
      // Save at least one revision to better simulate a real site.
      $node->setNewRevision();
      $node->save();
      $node = node_load($nid, TRUE);
      $this->assertNotEqual($nid, $node->vid, 'Node revision exists.');
    }

    // Attach a file to the node.
    $field = field_info_field($field_name);
    $name = 'files[' . $field_name . '_' . $langcode . '_0]';
    if ($field['cardinality'] != 1) {
      $name .= '[]';
    }
    $edit[$name] = drupal_realpath($file->getFileUri());
    $this->drupalPost("node/$nid/edit", $edit, t('Save and keep published'));

    return $nid;
  }

  /**
   * Removes a file from a node.
   *
   * Note that if replacing a file, it must first be removed then added again.
   */
  function removeNodeFile($nid, $new_revision = TRUE) {
    $edit = array(
      'revision' => (string) (int) $new_revision,
    );

    $this->drupalPost('node/' . $nid . '/edit', array(), t('Remove'));
    $this->drupalPost(NULL, $edit, t('Save and keep published'));
  }

  /**
   * Replaces a file within a node.
   */
  function replaceNodeFile($file, $field_name, $nid, $new_revision = TRUE) {
    $edit = array(
      'files[' . $field_name . '_' . Language::LANGCODE_NOT_SPECIFIED . '_0]' => drupal_realpath($file->getFileUri()),
      'revision' => (string) (int) $new_revision,
    );

    $this->drupalPost('node/' . $nid . '/edit', array(), t('Remove'));
    $this->drupalPost(NULL, $edit, t('Save and keep published'));
  }

  /**
   * Asserts that a file exists physically on disk.
   */
  function assertFileExists($file, $message = NULL) {
    $message = isset($message) ? $message : format_string('File %file exists on the disk.', array('%file' => $file->getFileUri()));
    $this->assertTrue(is_file($file->getFileUri()), $message);
  }

  /**
   * Asserts that a file exists in the database.
   */
  function assertFileEntryExists($file, $message = NULL) {
    $this->container->get('plugin.manager.entity')->getStorageController('file')->resetCache();
    $db_file = file_load($file->id());
    $message = isset($message) ? $message : format_string('File %file exists in database at the correct path.', array('%file' => $file->getFileUri()));
    $this->assertEqual($db_file->getFileUri(), $file->getFileUri(), $message);
  }

  /**
   * Asserts that a file does not exist on disk.
   */
  function assertFileNotExists($file, $message = NULL) {
    $message = isset($message) ? $message : format_string('File %file exists on the disk.', array('%file' => $file->getFileUri()));
    $this->assertFalse(is_file($file->getFileUri()), $message);
  }

  /**
   * Asserts that a file does not exist in the database.
   */
  function assertFileEntryNotExists($file, $message) {
    $this->container->get('plugin.manager.entity')->getStorageController('file')->resetCache();
    $message = isset($message) ? $message : format_string('File %file exists in database at the correct path.', array('%file' => $file->getFileUri()));
    $this->assertFalse(file_load($file->id()), $message);
  }

  /**
   * Asserts that a file's status is set to permanent in the database.
   */
  function assertFileIsPermanent(FileInterface $file, $message = NULL) {
    $message = isset($message) ? $message : format_string('File %file is permanent.', array('%file' => $file->getFileUri()));
    $this->assertTrue($file->isPermanent(), $message);
  }

}
