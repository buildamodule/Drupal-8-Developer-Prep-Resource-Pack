<?php

/**
 * @file
 * Contains \Drupal\edit\Tests\MetadataGeneratorTest.
 */

namespace Drupal\edit\Tests;

use Drupal\Core\Language\Language;
use Drupal\edit\EditorSelector;
use Drupal\edit\MetadataGenerator;
use Drupal\edit\Plugin\InPlaceEditorManager;
use Drupal\edit_test\MockEditEntityFieldAccessCheck;

/**
 * Test in-place field editing metadata.
 */
class MetadataGeneratorTest extends EditTestBase {

  /**
   * The manager for editor plugins.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $editorManager;

  /**
   * The metadata generator object to be tested.
   *
   * @var \Drupal\edit\MetadataGeneratorInterface.php
   */
  protected $metadataGenerator;

  /**
   * The editor selector object to be used by the metadata generator object.
   *
   * @var \Drupal\edit\EditorSelectorInterface
   */
  protected $editorSelector;

  /**
   * The access checker object to be used by the metadata generator object.
   *
   * @var \Drupal\edit\Access\EditEntityFieldAccessCheckInterface
   */
  protected $accessChecker;

  public static function getInfo() {
    return array(
      'name' => 'In-place field editing metadata',
      'description' => 'Tests in-place field editing metadata generation.',
      'group' => 'Edit',
    );
  }

  function setUp() {
    parent::setUp();

    $this->editorManager = $this->container->get('plugin.manager.edit.editor');
    $this->accessChecker = new MockEditEntityFieldAccessCheck();
    $this->editorSelector = new EditorSelector($this->editorManager, $this->container->get('plugin.manager.field.formatter'));
    $this->metadataGenerator = new MetadataGenerator($this->accessChecker, $this->editorSelector, $this->editorManager);
  }

  /**
   * Tests a simple entity type, with two different simple fields.
   */
  function testSimpleEntityType() {
    $field_1_name = 'field_text';
    $field_1_label = 'Simple text field';
    $this->createFieldWithInstance(
      $field_1_name, 'text', 1, $field_1_label,
      // Instance settings.
      array('text_processing' => 0),
      // Widget type & settings.
      'text_textfield',
      array('size' => 42),
      // 'default' formatter type & settings.
      'text_default',
      array()
    );
    $field_2_name = 'field_nr';
    $field_2_label = 'Simple number field';
    $this->createFieldWithInstance(
      $field_2_name, 'number_integer', 1, $field_2_label,
      // Instance settings.
      array(),
      // Widget type & settings.
      'number',
      array(),
      // 'default' formatter type & settings.
      'number_integer',
      array()
    );

    // Create an entity with values for this text field.
    $this->entity = entity_create('entity_test', array());
    $this->is_new = TRUE;
    $this->entity->{$field_1_name}->value = 'Test';
    $this->entity->{$field_2_name}->value = 42;
    $this->entity->save();
    $entity = entity_load('entity_test', $this->entity->id());

    // Verify metadata for field 1.
    $instance_1 = field_info_instance($entity->entityType(), $field_1_name, $entity->bundle());
    $metadata_1 = $this->metadataGenerator->generateField($entity, $instance_1, Language::LANGCODE_NOT_SPECIFIED, 'default');
    $expected_1 = array(
      'access' => TRUE,
      'label' => 'Simple text field',
      'editor' => 'direct',
      'aria' => 'Entity entity_test 1, field Simple text field',
    );
    $this->assertEqual($expected_1, $metadata_1, 'The correct metadata is generated for the first field.');

    // Verify metadata for field 2.
    $instance_2 = field_info_instance($entity->entityType(), $field_2_name, $entity->bundle());
    $metadata_2 = $this->metadataGenerator->generateField($entity, $instance_2, Language::LANGCODE_NOT_SPECIFIED, 'default');
    $expected_2 = array(
      'access' => TRUE,
      'label' => 'Simple number field',
      'editor' => 'form',
      'aria' => 'Entity entity_test 1, field Simple number field',
    );
    $this->assertEqual($expected_2, $metadata_2, 'The correct metadata is generated for the second field.');
  }

  function testEditorWithCustomMetadata() {
    $this->installSchema('system', 'url_alias');
    $this->enableModules(array('user', 'filter'));

    // Enable edit_test module so that the WYSIWYG editor becomes available.
    $this->enableModules(array('edit_test'));

    // Create a rich text field.
    $field_name = 'field_rich';
    $field_label = 'Rich text field';
    $this->createFieldWithInstance(
      $field_name, 'text', 1, $field_label,
      // Instance settings.
      array('text_processing' => 1),
      // Widget type & settings.
      'text_textfield',
      array('size' => 42),
      // 'default' formatter type & settings.
      'text_default',
      array()
    );

    // Create a text format.
    $full_html_format = entity_create('filter_format', array(
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => array(
        'filter_htmlcorrector' => array('status' => 1),
      ),
    ));
    $full_html_format->save();

    // Create an entity with values for this rich text field.
    $this->entity = entity_create('entity_test', array());
    $this->entity->{$field_name}->value = 'Test';
    $this->entity->{$field_name}->format = 'full_html';
    $this->entity->save();
    $entity = entity_load('entity_test', $this->entity->id());

    // Verify metadata.
    $instance = field_info_instance($entity->entityType(), $field_name, $entity->bundle());
    $metadata = $this->metadataGenerator->generateField($entity, $instance, Language::LANGCODE_NOT_SPECIFIED, 'default');
    $expected = array(
      'access' => TRUE,
      'label' => 'Rich text field',
      'editor' => 'wysiwyg',
      'aria' => 'Entity entity_test 1, field Rich text field',
      'custom' => array(
        'format' => 'full_html'
      ),
    );
    $this->assertEqual($expected, $metadata, 'The correct metadata (including custom metadata) is generated.');
  }
}
