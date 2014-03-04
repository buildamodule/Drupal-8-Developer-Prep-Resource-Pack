<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Entity\EntityQueryTest.
 */

namespace Drupal\system\Tests\Entity;

use Drupal\Core\Language\Language;

/**
 * Tests the basic Entity API.
 */
class EntityQueryTest extends EntityUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('field_test', 'language');

  /**
   * @var array
   */
  protected $queryResults;

  /**
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $factory;

  /**
   * Field name for the greetings field.
   *
   * @var string
   */
  public $greetings;

  /**
   * Field name for the greetings field.
   *
   * @var string
   */
  public $figures;

  public static function getInfo() {
    return array(
      'name' => 'Entity Query',
      'description' => 'Tests Entity Query functionality.',
      'group' => 'Entity API',
    );
  }

  function setUp() {
    parent::setUp();
    $this->installSchema('entity_test', array('entity_test_mulrev', 'entity_test_mulrev_property_data', 'entity_test_mulrev_property_revision'));
    $this->installSchema('system', array('variable'));
    $this->installConfig(array('language'));

    $figures = drupal_strtolower($this->randomName());
    $greetings = drupal_strtolower($this->randomName());
    foreach (array($figures => 'shape', $greetings => 'text') as $field_name => $field_type) {
      $field = entity_create('field_entity', array(
        'field_name' => $field_name,
        'type' => $field_type,
        'cardinality' => 2,
        'translatable' => TRUE,
      ));
      $field->save();
      $fields[] = $field;
    }
    $bundles = array();
    for ($i = 0; $i < 2; $i++) {
      // For the sake of tablesort, make sure the second bundle is higher than
      // the first one. Beware: MySQL is not case sensitive.
      do {
        $bundle = $this->randomName();
      } while ($bundles && strtolower($bundles[0]) >= strtolower($bundle));
      entity_test_create_bundle($bundle);
      foreach ($fields as $field) {
        entity_create('field_instance', array(
          'field_name' => $field->id(),
          'entity_type' => 'entity_test_mulrev',
          'bundle' => $bundle,
        ))->save();
      }
      $bundles[] = $bundle;
    }
    // Each unit is a list of field name, langcode and a column-value array.
    $units[] = array($figures, 'en', array(
      'color' => 'red',
      'shape' => 'triangle',
    ));
    $units[] = array($figures, 'en', array(
      'color' => 'blue',
      'shape' => 'circle',
    ));
    // To make it easier to test sorting, the greetings get formats according
    // to their langcode.
    $units[] = array($greetings, 'tr', array(
      'value' => 'merhaba',
      'format' => 'format-tr'
    ));
    $units[] = array($greetings, 'pl', array(
      'value' => 'siema',
      'format' => 'format-pl'
    ));
    // Make these languages available to the greetings field.
    $langcode = new Language(array(
      'id' => 'en',
      'name' => $this->randomString(),
    ));
    language_save($langcode);
    $langcode = new Language(array(
      'id' => 'tr',
      'name' => $this->randomString(),
    ));
    language_save($langcode);
    $langcode = new Language(array(
      'id' => 'pl',
      'name' => $this->randomString(),
    ));
    language_save($langcode);
    $field_langcodes = &drupal_static('field_available_languages');
    $field_langcodes['entity_test_mulrev'][$greetings] = array('tr', 'pl');
    // Calculate the cartesian product of the unit array by looking at the
    // bits of $i and add the unit at the bits that are 1. For example,
    // decimal 13 is binary 1101 so unit 3,2 and 0 will be added to the
    // entity.
    for ($i = 1; $i <= 15; $i++) {
      $entity = entity_create('entity_test_mulrev', array(
        'type' => $bundles[$i & 1],
        'name' => $this->randomName(),
        'langcode' => 'en',
      ));
      // Make sure the name is set for every language that we might create.
      foreach (array('tr', 'pl') as $langcode) {
        $entity->getTranslation($langcode)->name = $this->randomName();
      }
      foreach (array_reverse(str_split(decbin($i))) as $key => $bit) {
        if ($bit) {
          list($field_name, $langcode, $values) = $units[$key];
          $entity->getTranslation($langcode)->{$field_name}[] = $values;
        }
      }
      $entity->save();
    }
    $this->figures = $figures;
    $this->greetings = $greetings;
    $this->factory = \Drupal::service('entity.query');
  }

  /**
   * Test basic functionality.
   */
  function testEntityQuery() {
    $greetings = $this->greetings;
    $figures = $this->figures;
    $this->queryResults = $this->factory->get('entity_test_mulrev')
      ->exists($greetings, 'tr')
      ->condition("$figures.color", 'red')
      ->sort('id')
      ->execute();
    // As unit 0 was the red triangle and unit 2 was the turkish greeting,
    // bit 0 and bit 2 needs to be set.
    $this->assertResult(5, 7, 13, 15);

    $query = $this->factory->get('entity_test_mulrev', 'OR')
      ->exists($greetings, 'tr')
      ->condition("$figures.color", 'red')
      ->sort('id');
    $count_query = clone $query;
    $this->assertEqual(12, $count_query->count()->execute());
    $this->queryResults = $query->execute();
    // Now bit 0 (1, 3, 5, 7, 9, 11, 13, 15) or bit 2 (4, 5, 6, 7, 12, 13, 14,
    // 15) needs to be set.
    $this->assertResult(1, 3, 4, 5, 6, 7, 9, 11, 12, 13, 14, 15);

    // Test cloning of query conditions.
    $query = $this->factory->get('entity_test_mulrev')
      ->condition("$figures.color", 'red')
      ->sort('id');
    $cloned_query = clone $query;
    $cloned_query
      ->condition("$figures.shape", 'circle');
    // Bit 0 (1, 3, 5, 7, 9, 11, 13, 15) needs to be set.
    $this->queryResults = $query->execute();
    $this->assertResult(1, 3, 5, 7, 9, 11, 13, 15);
    // No red color has a circle shape.
    $this->queryResults = $cloned_query->execute();
    $this->assertResult();

    $query = $this->factory->get('entity_test_mulrev');
    $group = $query->orConditionGroup()
      ->exists($greetings, 'tr')
      ->condition("$figures.color", 'red');
    $this->queryResults = $query
      ->condition($group)
      ->condition("$greetings.value", 'sie', 'STARTS_WITH')
      ->sort('revision_id')
      ->execute();
    // Bit 3 and (bit 0 or 2) -- the above 8 part of the above.
    $this->assertResult(9, 11, 12, 13, 14, 15);

    // No figure has both the colors blue and red at the same time.
    $this->queryResults = $this->factory->get('entity_test_mulrev')
      ->condition("$figures.color", 'blue')
      ->condition("$figures.color", 'red')
      ->sort('id')
      ->execute();
    $this->assertResult();

    // But an entity might have a red and a blue figure both.
    $query = $this->factory->get('entity_test_mulrev');
    $group_blue = $query->andConditionGroup()->condition("$figures.color", 'blue');
    $group_red = $query->andConditionGroup()->condition("$figures.color", 'red');
    $this->queryResults = $query
      ->condition($group_blue)
      ->condition($group_red)
      ->sort('revision_id')
      ->execute();
    // Unit 0 and unit 1, so bits 0 1.
    $this->assertResult(3, 7, 11, 15);

    $this->queryResults = $this->factory->get('entity_test_mulrev')
      ->exists("$figures.color")
      ->notExists("$greetings.value")
      ->sort('id')
      ->execute();
    // Bit 0 or 1 is on but 2 and 3 are not.
    $this->assertResult(1, 2, 3);
    // Now update the 'merhaba' string to xsiemax which is not a meaningful
    // word but allows us to test revisions and string operations.
    $ids = $this->factory->get('entity_test_mulrev')
      ->condition("$greetings.value", 'merhaba')
      ->execute();
    $entities = entity_load_multiple('entity_test_mulrev', $ids);
    foreach ($entities as $entity) {
      $entity->setNewRevision();
      $entity->getTranslation('tr')->$greetings->value = 'xsiemax';
      $entity->save();
    }
    // When querying current revisions, this string is no longer found.
    $this->queryResults = $this->factory->get('entity_test_mulrev')
      ->condition("$greetings.value", 'merhaba')
      ->execute();
    $this->assertResult();
    $this->queryResults = $this->factory->get('entity_test_mulrev')
      ->condition("$greetings.value", 'merhaba')
      ->age(FIELD_LOAD_REVISION)
      ->sort('revision_id')
      ->execute();
    // Bit 2 needs to be set.
    // The keys must be 16-23 because the first batch stopped at 15 so the
    // second started at 16 and eight entities were saved.
    $assert = $this->assertRevisionResult(range(16, 23), array(4, 5, 6, 7, 12, 13, 14, 15));
    $results = $this->factory->get('entity_test_mulrev')
      ->condition("$greetings.value", 'siema', 'CONTAINS')
      ->sort('id')
      ->execute();
    // This is the same as the previous one because xsiemax replaced merhaba
    // but also it contains the entities that siema originally but not
    // merhaba.
    $assert = array_slice($assert, 0, 4, TRUE) + array(8 => '8', 9 => '9', 10 => '10', 11 => '11') + array_slice($assert, 4, 4, TRUE);
    $this->assertIdentical($results, $assert);
    $results = $this->factory->get('entity_test_mulrev')
      ->condition("$greetings.value", 'siema', 'STARTS_WITH')
      ->execute();
    // Now we only get the ones that originally were siema, entity id 8 and
    // above.
    $this->assertIdentical($results, array_slice($assert, 4, 8, TRUE));
    $results = $this->factory->get('entity_test_mulrev')
      ->condition("$greetings.value", 'a', 'ENDS_WITH')
      ->execute();
    // It is very important that we do not get the ones which only have
    // xsiemax despite originally they were merhaba, ie. ended with a.
    $this->assertIdentical($results, array_slice($assert, 4, 8, TRUE));
    $results = $this->factory->get('entity_test_mulrev')
      ->condition("$greetings.value", 'a', 'ENDS_WITH')
      ->age(FIELD_LOAD_REVISION)
      ->sort('id')
      ->execute();
    // Now we get everything.
    $this->assertIdentical($results, $assert);
  }

  /**
   * Test sort().
   *
   * Warning: this is complicated.
   */
  function testSort() {
    $greetings = $this->greetings;
    $figures = $this->figures;
    // Order up and down on a number.
    $this->queryResults = $this->factory->get('entity_test_mulrev')
      ->sort('id')
      ->execute();
    $this->assertResult(range(1, 15));
    $this->queryResults = $this->factory->get('entity_test_mulrev')
      ->sort('id', 'DESC')
      ->execute();
    $this->assertResult(range(15, 1));
    $query = $this->factory->get('entity_test_mulrev')
      ->sort("$figures.color")
      ->sort("$greetings.format")
      ->sort('id');
    // As we do not have any conditions, here are the possible colors and
    // language codes, already in order, with the first occurence of the
    // entity id marked with *:
    // 8  NULL pl *
    // 12 NULL pl *

    // 4  NULL tr *
    // 12 NULL tr

    // 2  blue NULL *
    // 3  blue NULL *

    // 10 blue pl *
    // 11 blue pl *
    // 14 blue pl *
    // 15 blue pl *

    // 6  blue tr *
    // 7  blue tr *
    // 14 blue tr
    // 15 blue tr

    // 1  red  NULL
    // 3  red  NULL

    // 9  red  pl *
    // 11 red  pl
    // 13 red  pl *
    // 15 red  pl

    // 5  red  tr *
    // 7  red  tr
    // 13 red  tr
    // 15 red  tr
    $count_query = clone $query;
    $this->assertEqual(15, $count_query->count()->execute());
    $this->queryResults = $query->execute();
    $this->assertResult(8, 12, 4, 2, 3, 10, 11, 14, 15, 6, 7, 1, 9, 13, 5);

    // Test the pager by setting element #1 to page 2 with a page size of 4.
    // Results will be #8-12 from above.
    $_GET['page'] = '0,2';
    $this->queryResults = $this->factory->get('entity_test_mulrev')
      ->sort("$figures.color")
      ->sort("$greetings.format")
      ->sort('id')
      ->pager(4, 1)
      ->execute();
    $this->assertResult(15, 6, 7, 1);

    // Now test the reversed order.
    $query = $this->factory->get('entity_test_mulrev')
      ->sort("$figures.color", 'DESC')
      ->sort("$greetings.format", 'DESC')
      ->sort('id', 'DESC');
    $count_query = clone $query;
    $this->assertEqual(15, $count_query->count()->execute());
    $this->queryResults = $query->execute();
    $this->assertResult(15, 13, 7, 5, 11, 9, 3, 1, 14, 6, 10, 2, 12, 4, 8);
  }

  /**
   * Test tablesort().
   */
  protected function testTableSort() {
    // While ordering on bundles do not give us a definite order, we can still
    // assert that all entities from one bundle are after the other as the
    // order dictates.
    $_GET['sort'] = 'asc';
    $_GET['order'] = 'Type';
    $header = array(
      'id' => array('data' => 'Id', 'specifier' => 'id'),
      'type' => array('data' => 'Type', 'specifier' => 'type'),
    );

    $this->queryResults = array_values($this->factory->get('entity_test_mulrev')
      ->tableSort($header)
      ->execute());
    $this->assertBundleOrder('asc');
    $_GET['sort'] = 'desc';
    $header = array(
      'id' => array('data' => 'Id', 'specifier' => 'id'),
      'type' => array('data' => 'Type', 'specifier' => 'type'),
    );
    $this->queryResults = array_values($this->factory->get('entity_test_mulrev')
      ->tableSort($header)
      ->execute());
    $this->assertBundleOrder('desc');
    // Ordering on ID is definite, however.
    $_GET['order'] = 'Id';
    $this->queryResults = $this->factory->get('entity_test_mulrev')
      ->tableSort($header)
      ->execute();
    $this->assertResult(range(15, 1));
  }

  /**
   * Test entity count query.
   */
  protected function testCount() {
    // Attach the existing 'figures' field to a second entity type so that we
    // can test whether cross entity type fields produce the correct query.
    $field_name = $this->figures;
    $bundle = $this->randomName();
    entity_create('field_instance', array(
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => $bundle,
    ))->save();

    $entity = entity_create('entity_test', array(
      'id' => 1,
      'type' => $bundle,
    ));
    $entity->enforceIsNew();
    $entity->setNewRevision();
    $entity->save();
    // As the single entity of this type we just saved does not have a value
    // in the color field, the result should be 0.
    $count = $this->factory->get('entity_test')
      ->exists("$field_name.color")
      ->count()
      ->execute();
     $this->assertFalse($count);
  }

  protected function assertResult() {
    $assert = array();
    $expected = func_get_args();
    if ($expected && is_array($expected[0])) {
      $expected = $expected[0];
    }
    foreach ($expected as $binary) {
      $assert[$binary] = strval($binary);
    }
    $this->assertIdentical($this->queryResults, $assert);
  }

  protected function assertRevisionResult($keys, $expected) {
    $assert = array();
    foreach ($expected as $key => $binary) {
      $assert[$keys[$key]] = strval($binary);
    }
    $this->assertIdentical($this->queryResults, $assert);
    return $assert;
  }

  protected function assertBundleOrder($order) {
    // This loop is for bundle1 entities.
    for ($i = 1; $i <= 15; $i +=2) {
      $ok = TRUE;
      $index1 = array_search($i, $this->queryResults);
      $this->assertNotIdentical($index1, FALSE, "$i found at $index1.");
      // This loop is for bundle2 entities.
      for ($j = 2; $j <= 15; $j += 2) {
        if ($ok) {
          if ($order == 'asc') {
            $ok = $index1 > array_search($j, $this->queryResults);
          }
          else {
            $ok = $index1 < array_search($j, $this->queryResults);
          }
        }
      }
      $this->assertTrue($ok, format_string("$i is after all entities in bundle2"));
    }
  }

  /**
   * Test adding a tag and metadata to the Entity query object.
   *
   * The tags and metadata should propogate to the SQL query object.
   */
  function testMetaData() {
    $query = \Drupal::entityQuery('entity_test_mulrev');
    $query
      ->addTag('efq_metadata_test')
      ->addMetaData('foo', 'bar')
      ->execute();

    global $efq_test_metadata;
    $this->assertEqual($efq_test_metadata, 'bar', 'Tag and metadata propogated to the SQL query object.');
  }
}
