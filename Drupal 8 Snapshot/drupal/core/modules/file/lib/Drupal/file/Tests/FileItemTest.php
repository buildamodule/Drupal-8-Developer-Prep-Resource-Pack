<?php

/**
 * @file
 * Contains \Drupal\file\Tests\FileItemTest.
 */

namespace Drupal\file\Tests;

use Drupal\Core\Entity\Field\FieldItemInterface;
use Drupal\Core\Entity\Field\FieldInterface;
use Drupal\field\Tests\FieldUnitTestBase;

/**
 * Tests the new entity API for the file field type.
 */
class FileItemTest extends FieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('file');

  /**
   * Created file entity.
   *
   * @var \Drupal\file\Plugin\Core\Entity\File
   */
  protected $file;

  public static function getInfo() {
    return array(
      'name' => 'File field item API',
      'description' => 'Tests using entity fields of the file field type.',
      'group' => 'File',
    );
  }

  public function setUp() {
    parent::setUp();

    $this->installSchema('file', 'file_managed');
    $this->installSchema('file', 'file_usage');

    entity_create('field_entity', array(
      'field_name' => 'file_test',
      'type' => 'file',
      'cardinality' => FIELD_CARDINALITY_UNLIMITED,
    ))->save();
    entity_create('field_instance', array(
      'entity_type' => 'entity_test',
      'field_name' => 'file_test',
      'bundle' => 'entity_test',
    ))->save();
    file_put_contents('public://example.txt', $this->randomName());
    $this->file = entity_create('file', array(
      'uri' => 'public://example.txt',
    ));
    $this->file->save();
  }

  /**
   * Tests using entity fields of the file field type.
   */
  public function testFileItem() {
    // Create a test entity with the
    $entity = entity_create('entity_test', array());
    $entity->file_test->target_id = $this->file->id();
    $entity->file_test->display = 1;
    $entity->file_test->description = $description = $this->randomName();
    $entity->name->value = $this->randomName();
    $entity->save();

    $entity = entity_load('entity_test', $entity->id());
    $this->assertTrue($entity->file_test instanceof FieldInterface, 'Field implements interface.');
    $this->assertTrue($entity->file_test[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEqual($entity->file_test->target_id, $this->file->id());
    $this->assertEqual($entity->file_test->display, 1);
    $this->assertEqual($entity->file_test->description, $description);
    $this->assertEqual($entity->file_test->entity->getFileUri(), $this->file->getFileUri());
    $this->assertEqual($entity->file_test->entity->id(), $this->file->id());
    $this->assertEqual($entity->file_test->entity->uuid(), $this->file->uuid());

    // Make sure the computed files reflects updates to the file.
    file_put_contents('public://example-2.txt', $this->randomName());
    $file2 = entity_create('file', array(
      'uri' => 'public://example-2.txt',
    ));
    $file2->save();

    $entity->file_test->target_id = $file2->id();
    $this->assertEqual($entity->file_test->entity->id(), $file2->id());
    $this->assertEqual($entity->file_test->entity->getFileUri(), $file2->getFileUri());
  }

}
