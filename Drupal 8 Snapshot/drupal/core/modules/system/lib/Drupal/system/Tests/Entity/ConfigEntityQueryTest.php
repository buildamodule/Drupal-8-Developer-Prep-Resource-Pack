<?php

/**
 * @file
 * Contains \Drupal\system\Tests\ConfigEntityQueryTest.
 */

namespace Drupal\system\Tests\Entity;

use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests the config entity query.
 *
 * @see \Drupal\Core\Config\Entity\Query
 */
class ConfigEntityQueryTest extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  static $modules = array('config_test');

  /**
   * Stores the search results for alter comparision.
   *
   * @var array
   */
  protected $queryResults;

  /**
   * The query factory used to construct all queries in the test.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $factory;

  /**
   * Stores all config entities created for the test.
   *
   * @var array
   */
  protected $entities;

  public static function getInfo() {
    return array(
      'name' => 'Config Entity Query',
      'description' => 'Tests Config Entity Query functionality.',
      'group' => 'Configuration',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->entities = array();
    $this->enableModules(array('entity'), TRUE);
    $this->factory = $this->container->get('entity.query');

    // These two are here to make sure that matchArray needs to go over several
    // non-matches on every levels.
    $array['level1']['level2a'] = 9;
    $array['level1a']['level2'] = 9;
    // The tests match array.level1.level2.
    $array['level1']['level2'] = 1;
    $entity = entity_create('config_query_test', array(
      'label' => $this->randomName(),
      'id' => '1',
      'number' => 31,
      'array' => $array,
    ));
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1']['level2'] = 2;
    $entity = entity_create('config_query_test', array(
      'label' => $this->randomName(),
      'id' => '2',
      'number' => 41,
      'array' => $array,
    ));
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1']['level2'] = 1;
    $entity = entity_create('config_query_test', array(
      'label' => 'test_prefix_' . $this->randomName(),
      'id' => '3',
      'number' => 59,
      'array' => $array,
    ));
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1']['level2'] = 2;
    $entity = entity_create('config_query_test', array(
      'label' => $this->randomName() . '_test_suffix',
      'id' => '4',
      'number' => 26,
      'array' => $array,
    ));
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1']['level2'] = 3;
    $entity = entity_create('config_query_test', array(
      'label' => $this->randomName() . '_test_contains_' . $this->randomName(),
      'id' => '5',
      'number' => 53,
      'array' => $array,
    ));
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();
  }

  /**
   * Tests basic functionality.
   */
  public function testConfigEntityQuery() {
    // Run a test without any condition.
    $this->queryResults = $this->factory->get('config_query_test')
      ->execute();
    $this->assertResults(array('1', '2', '3', '4', '5'));
    // No conditions, OR.
    $this->queryResults = $this->factory->get('config_query_test', 'OR')
      ->execute();
    $this->assertResults(array('1', '2', '3', '4', '5'));

    // Filter by ID with equality.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', '3')
      ->execute();
    $this->assertResults(array('3'));

    // Filter by label with a known prefix.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('label', 'test_prefix', 'STARTS_WITH')
      ->execute();
    $this->assertResults(array('3'));

    // Filter by label with a known suffix.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('label', 'test_suffix', 'ENDS_WITH')
      ->execute();
    $this->assertResults(array('4'));

    // Filter by label with a known containing word.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('label', 'test_contains', 'CONTAINS')
      ->execute();
    $this->assertResults(array('5'));

    // Filter by ID with the IN operator.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', array('2', '3'), 'IN')
      ->execute();
    $this->assertResults(array('2', '3'));

    // Filter by ID with the implicit IN operator.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', array('2', '3'))
      ->execute();
    $this->assertResults(array('2', '3'));

    // Filter by ID with the > operator.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', '3', '>')
      ->execute();
    $this->assertResults(array('4', '5'));

    // Filter by ID with the >= operator.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', '3', '>=')
      ->execute();
    $this->assertResults(array('3', '4', '5'));

    // Filter by ID with the <> operator.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', '3', '<>')
      ->execute();
    $this->assertResults(array('1', '2', '4', '5'));

    // Filter by ID with the < operator.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', '3', '<')
      ->execute();
    $this->assertResults(array('1', '2'));

    // Filter by ID with the <= operator.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', '3', '<=')
      ->execute();
    $this->assertResults(array('1', '2', '3'));

    // Filter by two conditions on the same field.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('label', 'test_pref', 'STARTS_WITH')
      ->condition('label', 'test_prefix', 'STARTS_WITH')
      ->execute();
    $this->assertResults(array('3'));

    // Filter by two conditions on different fields. The first query matches for
    // a different ID, so the result is empty.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('label', 'test_prefix', 'STARTS_WITH')
      ->condition('id', '5')
      ->execute();
    $this->assertResults(array());

    // Filter by two different conditions on different fields. This time the
    // first condition matches on one item, but the second one does as well.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('label', 'test_prefix', 'STARTS_WITH')
      ->condition('id', '3')
      ->execute();
    $this->assertResults(array('3'));

    // Filter by two different conditions, of which the first one matches for
    // every entry, the second one as well, but just the third one filters so
    // that just two are left.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', '1', '>=')
      ->condition('number', 10, '>=')
      ->condition('number', 50, '>=')
      ->execute();
    $this->assertResults(array('3', '5'));

    // Filter with an OR condition group.
    $this->queryResults = $this->factory->get('config_query_test', 'OR')
      ->condition('id', 1)
      ->condition('id', '2')
      ->execute();
    $this->assertResults(array('1', '2'));

    // Simplify it with IN.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', array('1', '2'))
      ->execute();
    $this->assertResults(array('1', '2'));
    // Try explicit IN.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', array('1', '2'), 'IN')
      ->execute();
    $this->assertResults(array('1', '2'));
    // Try not IN.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', array('1', '2'), 'NOT IN')
      ->execute();
    $this->assertResults(array('3', '4', '5'));

    // Filter with an OR condition group on different fields.
    $this->queryResults = $this->factory->get('config_query_test', 'OR')
      ->condition('id', 1)
      ->condition('number', 41)
      ->execute();
    $this->assertResults(array('1', '2'));

    // Filter with an OR condition group on different fields but matching on the
    // same entity.
    $this->queryResults = $this->factory->get('config_query_test', 'OR')
      ->condition('id', 1)
      ->condition('number', 31)
      ->execute();
    $this->assertResults(array('1'));

    // NO simple conditions, YES complex conditions, 'AND'.
    $query = $this->factory->get('config_query_test', 'AND');
    $and_condition_1 = $query->orConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->orConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[3]->label);
    $this->queryResults = $query
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->execute();
    $this->assertResults(array('1'));

    // NO simple conditions, YES complex conditions, 'OR'.
    $query = $this->factory->get('config_query_test', 'OR');
    $and_condition_1 = $query->andConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->andConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[1]->label);
    $this->queryResults = $query
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->execute();
    $this->assertResults(array('1', '2'));

    // YES simple conditions, YES complex conditions, 'AND'.
    $query = $this->factory->get('config_query_test', 'AND');
    $and_condition_1 = $query->orConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->orConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[3]->label);
    $this->queryResults = $query
      ->condition('number', 31)
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->execute();
    $this->assertResults(array('1'));

    // YES simple conditions, YES complex conditions, 'OR'.
    $query = $this->factory->get('config_query_test', 'OR');
    $and_condition_1 = $query->orConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->orConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[3]->label);
    $this->queryResults = $query
      ->condition('number', 53)
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->execute();
    $this->assertResults(array('1', '2', '4', '5'));

    // Test the exists and notExists conditions.
    $this->queryResults = $this->factory->get('config_query_test')
      ->exists('id')
      ->execute();
    $this->assertResults(array('1', '2', '3', '4', '5'));

    $this->queryResults = $this->factory->get('config_query_test')
      ->exists('non-existent')
      ->execute();
    $this->assertResults(array());

    $this->queryResults = $this->factory->get('config_query_test')
      ->notExists('id')
      ->execute();
    $this->assertResults(array());

    $this->queryResults = $this->factory->get('config_query_test')
      ->notExists('non-existent')
      ->execute();
    $this->assertResults(array('1', '2', '3', '4', '5'));
  }

  /**
   * Tests count query.
   */
  protected function testCount() {
    // Test count on no conditions.
    $count = $this->factory->get('config_query_test')
      ->count()
      ->execute();
    $this->assertIdentical($count, count($this->entities));

    // Test count on a complex query.
    $query = $this->factory->get('config_query_test', 'OR');
    $and_condition_1 = $query->andConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->andConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[1]->label);
    $count = $query
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->count()
      ->execute();
    $this->assertIdentical($count, 2);
  }

  /**
   * Tests sorting and range on config entity queries.
   */
  protected function testSortRange() {
    // Sort by simple ascending/descending.
    $this->queryResults = $this->factory->get('config_query_test')
      ->sort('number', 'DESC')
      ->execute();
    $this->assertIdentical(array_values($this->queryResults), array('3', '5', '2', '1', '4'));

    $this->queryResults = $this->factory->get('config_query_test')
      ->sort('number', 'ASC')
      ->execute();
    $this->assertIdentical(array_values($this->queryResults), array('4', '1', '2', '5', '3'));

    // Apply some filters and sort.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', '3', '>')
      ->sort('number', 'DESC')
      ->execute();
    $this->assertIdentical(array_values($this->queryResults), array('5', '4'));

    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('id', '3', '>')
      ->sort('number', 'ASC')
      ->execute();
    $this->assertIdentical(array_values($this->queryResults), array('4', '5'));

    // Apply a pager and sort.
    $this->queryResults = $this->factory->get('config_query_test')
      ->sort('number', 'DESC')
      ->range('2', '2')
      ->execute();
    $this->assertIdentical(array_values($this->queryResults), array('2', '1'));

    $this->queryResults = $this->factory->get('config_query_test')
      ->sort('number', 'ASC')
      ->range('2', '2')
      ->execute();
    $this->assertIdentical(array_values($this->queryResults), array('2', '5'));

    // Add a range to a query without a start parameter.
    $this->queryResults = $this->factory->get('config_query_test')
      ->range(0, '3')
      ->sort('id', 'ASC')
      ->execute();
    $this->assertIdentical(array_values($this->queryResults), array('1', '2', '3'));

    // Apply a pager with limit 4.
    $this->queryResults = $this->factory->get('config_query_test')
      ->pager('4', 0)
      ->sort('id', 'ASC')
      ->execute();
    $this->assertIdentical(array_values($this->queryResults), array('1', '2', '3', '4'));
  }

  /**
   * Tests dotted path matching.
   */
  protected function testDotted() {
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('array.level1.*', 1)
      ->execute();
    $this->assertResults(array('1', '3'));
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('*.level1.level2', 2)
      ->execute();
    $this->assertResults(array('2', '4'));
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('array.level1.*', 3)
      ->execute();
    $this->assertResults(array('5'));
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('array.level1.level2', 3)
      ->execute();
    $this->assertResults(array('5'));
    // Make sure that values on the wildcard level do not match if if there are
    // sub-keys defined. This must not find anything even if entity 2 has a
    // top-level key number with value 41.
    $this->queryResults = $this->factory->get('config_query_test')
      ->condition('*.level1.level2', 41)
      ->execute();
    $this->assertResults(array());
  }

  /**
   * Asserts the results as expected regardless of order.
   *
   * @param array $expected
   *   Array of expected entity IDs.
   */
  protected function assertResults($expected) {
    $this->assertIdentical(count($this->queryResults), count($expected));
    foreach ($expected as $value) {
      // This also tests whether $this->queryResults[$value] is even set at all.
      $this->assertIdentical($this->queryResults[$value], $value);
    }
  }

}
