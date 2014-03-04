<?php

/**
 * @file
 * Contains \Drupal\hal\Tests\NormalizerTestBase.
 */

namespace Drupal\hal\Tests;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Language\Language;
use Drupal\hal\Encoder\JsonEncoder;
use Drupal\hal\Normalizer\EntityNormalizer;
use Drupal\hal\Normalizer\EntityReferenceItemNormalizer;
use Drupal\hal\Normalizer\FieldItemNormalizer;
use Drupal\hal\Normalizer\FieldNormalizer;
use Drupal\rest\LinkManager\LinkManager;
use Drupal\rest\LinkManager\RelationLinkManager;
use Drupal\rest\LinkManager\TypeLinkManager;
use Drupal\simpletest\DrupalUnitTestBase;
use Symfony\Component\Serializer\Serializer;

/**
 * Test the HAL normalizer.
 */
abstract class NormalizerTestBase extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('entity', 'entity_test', 'entity_reference', 'field', 'field_sql_storage', 'hal', 'language', 'rest', 'serialization', 'system', 'text', 'user');

  /**
   * The mock serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The format being tested.
   *
   * @var string
   */
  protected $format = 'hal_json';

  /**
   * The class name of the test class.
   *
   * @var string
   */
  protected $entityClass = 'Drupal\entity_test\Plugin\Core\Entity\EntityTest';

  /**
   * Overrides \Drupal\simpletest\DrupalUnitTestBase::setup().
   */
  function setUp() {
    parent::setUp();
    $this->installSchema('system', array('variable', 'url_alias'));
    $this->installSchema('user', array('users'));
    $this->installSchema('entity_test', array('entity_test'));
    $this->installConfig(array('field', 'language'));

    // Add English as a language.
    $english = new Language(array(
      'id' => 'en',
      'name' => 'English',
    ));
    language_save($english);
    // Add German as a language.
    $german = new Language(array(
      'id' => 'de',
      'name' => 'Deutsch',
    ));
    language_save($german);

    // Create the test text field.
    entity_create('field_entity', array(
      'field_name' => 'field_test_text',
      'type' => 'text',
      'translatable' => FALSE,
    ))->save();
    entity_create('field_instance', array(
      'entity_type' => 'entity_test',
      'field_name' => 'field_test_text',
      'bundle' => 'entity_test',
    ))->save();

    // Create the test translatable field.
    entity_create('field_entity', array(
      'field_name' => 'field_test_translatable_text',
      'type' => 'text',
      'translatable' => TRUE,
    ))->save();
    entity_create('field_instance', array(
      'entity_type' => 'entity_test',
      'field_name' => 'field_test_translatable_text',
      'bundle' => 'entity_test',
    ))->save();

    // Create the test entity reference field.
    entity_create('field_entity', array(
      'translatable' => TRUE,
      'settings' => array(
        'target_type' => 'entity_test',
      ),
      'field_name' => 'field_test_entity_reference',
      'type' => 'entity_reference',
    ))->save();
    entity_create('field_instance', array(
      'entity_type' => 'entity_test',
      'field_name' => 'field_test_entity_reference',
      'bundle' => 'entity_test',
    ))->save();

    // Set up the mock serializer.
    $normalizers = array(
      new EntityNormalizer(),
      new EntityReferenceItemNormalizer(),
      new FieldItemNormalizer(),
      new FieldNormalizer(),
    );
    $link_manager = new LinkManager(new TypeLinkManager(new MemoryBackend('cache')), new RelationLinkManager(new MemoryBackend('cache')));
    foreach ($normalizers as $normalizer) {
      $normalizer->setLinkManager($link_manager);
    }
    $encoders = array(
      new JsonEncoder(),
    );
    $this->serializer = new Serializer($normalizers, $encoders);
  }

}
