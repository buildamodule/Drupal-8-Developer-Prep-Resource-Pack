<?php

/**
 * @file
 * Contains \Drupal\entity\Tests\ContentTranslationSyncImageTest.
 */

namespace Drupal\content_translation\Tests;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;

/**
 * Tests the Content Translation image field synchronization capability.
 */
class ContentTranslationSyncImageTest extends ContentTranslationTestBase {

  /**
   * The cardinality of the image field.
   *
   * @var int
   */
  protected $cardinality;

  /**
   * The test image files.
   *
   * @var array
   */
  protected $files;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('language', 'content_translation', 'entity_test', 'image');

  public static function getInfo() {
    return array(
      'name' => 'Image field synchronization',
      'description' => 'Tests the field synchronization behavior for the image field.',
      'group' => 'Content Translation UI',
    );
  }

  function setUp() {
    parent::setUp();
    $this->files = $this->drupalGetTestFiles('image');
  }

  /**
   * Creates the test image field.
   */
  protected function setupTestFields() {
    $this->fieldName = 'field_test_et_ui_image';
    $this->cardinality = 3;

    entity_create('field_entity', array(
      'field_name' => $this->fieldName,
      'type' => 'image',
      'cardinality' => $this->cardinality,
      'translatable' => TRUE,
    ))->save();

    entity_create('field_instance', array(
      'entity_type' => $this->entityType,
      'field_name' => $this->fieldName,
      'bundle' => $this->entityType,
      'label' => 'Test translatable image field',
      'settings' => array(
        'translation_sync' => array(
          'file' => FALSE,
          'alt' => 'alt',
          'title' => 'title',
        ),
      ),
    ))->save();
  }

  /**
   * Tests image field field synchronization.
   */
  function testImageFieldSync() {
    $default_langcode = $this->langcodes[0];
    $langcode = $this->langcodes[1];

    // Populate the required contextual values.
    $attributes = $this->container->get('request')->attributes;
    $attributes->set('source_langcode', $default_langcode);

    // Populate the test entity with some random initial values.
    $values = array(
      'name' => $this->randomName(),
      'user_id' => mt_rand(1, 128),
      'langcode' => $default_langcode,
    );
    $entity = entity_create($this->entityType, $values);

    // Create some file entities from the generated test files and store them.
    $values = array();
    for ($delta = 0; $delta < $this->cardinality; $delta++) {
      // For the default language use the same order for files and field items.
      $index = $delta;

      // Create the file entity for the image being processed and record its
      // identifier.
      $field_values = array(
        'uri' => $this->files[$index]->uri,
        'uid' => $GLOBALS['user']->id(),
        'status' => FILE_STATUS_PERMANENT,
      );
      $file = entity_create('file', $field_values);
      $file->save();
      $fid = $file->id();
      $this->files[$index]->fid = $fid;

      // Generate the item for the current image file entity and attach it to
      // the entity.
      $item = array(
        'target_id' => $fid,
        'alt' => $default_langcode . '_' . $fid . '_' . $this->randomName(),
        'title' => $default_langcode . '_' . $fid . '_' . $this->randomName(),
      );
      $entity->{$this->fieldName}->offsetGet($delta)->setValue($item);

      // Store the generated values keying them by fid for easier lookup.
      $values[$default_langcode][$fid] = $item;
    }
    $entity = $this->saveEntity($entity);

    // Create some field translations for the test image field. The translated
    // items will be one less than the original values to check that only the
    // translated ones will be preserved. In fact we want the same fids and
    // items order for both languages.
    $translation = $entity->getTranslation($langcode);
    for ($delta = 0; $delta < $this->cardinality - 1; $delta++) {
      // Simulate a field reordering: items are shifted of one position ahead.
      // The modulo operator ensures we start from the beginning after reaching
      // the maximum allowed delta.
      $index = ($delta + 1) % $this->cardinality;

      // Generate the item for the current image file entity and attach it to
      // the entity.
      $fid = $this->files[$index]->fid;
      $item = array(
        'target_id' => $fid,
        'alt' => $langcode . '_' . $fid . '_' . $this->randomName(),
        'title' => $langcode . '_' . $fid . '_' . $this->randomName(),
      );
      $translation->{$this->fieldName}->offsetGet($delta)->setValue($item);

      // Again store the generated values keying them by fid for easier lookup.
      $values[$langcode][$fid] = $item;
    }

    // Perform synchronization: the translation language is used as source,
    // while the default language is used as target.
    $entity = $this->saveEntity($translation);
    $translation = $entity->getTranslation($langcode);

    // Check that one value has been dropped from the original values.
    $assert = count($entity->{$this->fieldName}) == 2;
    $this->assertTrue($assert, 'One item correctly removed from the synchronized field values.');

    // Check that fids have been synchronized and translatable column values
    // have been retained.
    $fids = array();
    foreach ($entity->{$this->fieldName} as $delta => $item) {
      $value = $values[$default_langcode][$item->target_id];
      $source_item = $translation->{$this->fieldName}->offsetGet($delta);
      $assert = $item->target_id == $source_item->target_id && $item->alt == $value['alt'] && $item->title == $value['title'];
      $this->assertTrue($assert, format_string('Field item @fid has been successfully synchronized.', array('@fid' => $item->target_id)));
      $fids[$item->target_id] = TRUE;
    }

    // Check that the dropped value is the right one.
    $removed_fid = $this->files[0]->fid;
    $this->assertTrue(!isset($fids[$removed_fid]), format_string('Field item @fid has been correctly removed.', array('@fid' => $removed_fid)));

    // Add back an item for the dropped value and perform synchronization again.
    $values[$langcode][$removed_fid] = array(
      'target_id' => $removed_fid,
      'alt' => $langcode . '_' . $removed_fid . '_' . $this->randomName(),
      'title' => $langcode . '_' . $removed_fid . '_' . $this->randomName(),
    );
    $translation->{$this->fieldName}->setValue(array_values($values[$langcode]));
    // When updating an entity we do not have a source language defined.
    $attributes->remove('source_langcode');
    $entity = $this->saveEntity($translation);
    $translation = $entity->getTranslation($langcode);

    // Check that the value has been added to the default language.
    $assert = count($entity->{$this->fieldName}->getValue()) == 3;
    $this->assertTrue($assert, 'One item correctly added to the synchronized field values.');

    foreach ($entity->{$this->fieldName} as $delta => $item) {
      // When adding an item its value is copied over all the target languages,
      // thus in this case the source language needs to be used to check the
      // values instead of the target one.
      $fid_langcode = $item->target_id != $removed_fid ? $default_langcode : $langcode;
      $value = $values[$fid_langcode][$item->target_id];
      $source_item = $translation->{$this->fieldName}->offsetGet($delta);
      $assert = $item->target_id == $source_item->target_id && $item->alt == $value['alt'] && $item->title == $value['title'];
      $this->assertTrue($assert, format_string('Field item @fid has been successfully synchronized.', array('@fid' => $item->target_id)));
    }
  }

  /**
   * Saves the passed entity and reloads it, enabling compatibility mode.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be saved.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The saved entity.
   */
  protected function saveEntity(EntityInterface $entity) {
    $entity->save();
    $entity = entity_test_mul_load($entity->id(), TRUE);
    return $entity;
  }

}
