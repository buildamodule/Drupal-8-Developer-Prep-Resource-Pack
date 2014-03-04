<?php

/**
 * @file
 * Definition of Drupal\views\Tests\Handler\FilterCombineTest.
 */

namespace Drupal\views\Tests\Handler;

use Drupal\views\Tests\ViewUnitTestBase;

/**
 * Tests the combine filter handler.
 */
class FilterCombineTest extends ViewUnitTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_view');

  protected $column_map = array(
    'views_test_data_name' => 'name',
    'views_test_data_job' => 'job',
  );

  public static function getInfo() {
    return array(
      'name' => 'Filter: Combine',
      'description' => 'Tests the combine filter handler.',
      'group' => 'Views Handlers',
    );
  }

  public function testFilterCombineContains() {
    $view = views_get_view('test_view');
    $view->setDisplay();

    $fields = $view->displayHandlers->get('default')->getOption('fields');
    $view->displayHandlers->get('default')->overrideOption('fields', $fields + array(
      'job' => array(
        'id' => 'job',
        'table' => 'views_test_data',
        'field' => 'job',
        'relationship' => 'none',
      ),
    ));

    // Change the filtering.
    $view->displayHandlers->get('default')->overrideOption('filters', array(
      'age' => array(
        'id' => 'combine',
        'table' => 'views',
        'field' => 'combine',
        'relationship' => 'none',
        'operator' => 'contains',
        'fields' => array(
          'name',
          'job',
        ),
        'value' => 'ing',
      ),
    ));

    $this->executeView($view);
    $resultset = array(
      array(
        'name' => 'John',
        'job' => 'Singer',
      ),
      array(
        'name' => 'George',
        'job' => 'Singer',
      ),
      array(
        'name' => 'Ringo',
        'job' => 'Drummer',
      ),
      array(
        'name' => 'Ginger',
        'job' => NULL,
      ),
    );
    $this->assertIdenticalResultset($view, $resultset, $this->column_map);
  }

  /**
   * Additional data to test the NULL issue.
   */
  protected function dataSet() {
    $data_set = parent::dataSet();
    $data_set[] = array(
      'name' => 'Ginger',
      'age' => 25,
      'job' => NULL,
      'created' => gmmktime(0, 0, 0, 1, 2, 2000),
      'status' => 1,
    );
    return $data_set;
  }

  /**
   * Allow {views_test_data}.job to be NULL.
   */
  protected function schemaDefinition() {
    $schema = parent::schemaDefinition();
    unset($schema['views_test_data']['fields']['job']['not null']);
    return $schema;
  }

}
