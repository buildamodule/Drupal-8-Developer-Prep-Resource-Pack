<?php

/**
 * @file
 * Contains \Drupal\language\Tests\Views\FilterLanguageTest.
 */

namespace Drupal\language\Tests\Views;

use Drupal\Core\Language\Language;

/**
 * Tests the filter language handler.
 *
 * @see Drupal\language\Plugin\views\filter\Language
 */
class FilterLanguageTest extends LanguageTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_view');

  public static function getInfo() {
    return array(
      'name' => 'Filter: Language',
      'description' => 'Tests the filter language handler.',
      'group' => 'Views Handlers'
    );
  }

  /**
   * Tests the language filter.
   */
  public function testFilter() {
    $view = views_get_view('test_view');
    foreach (array('en' => 'John', 'xx-lolspeak' => 'George') as $langcode => $name) {
      $view->setDisplay();
      $view->displayHandlers->get('default')->overrideOption('filters', array(
        'langcode' => array(
          'id' => 'langcode',
          'table' => 'views_test_data',
          'field' => 'langcode',
          'value' => array($langcode),
        ),
      ));
      $this->executeView($view);

      $expected = array(array(
        'name' => $name,
      ));
      $this->assertIdenticalResultset($view, $expected, array('views_test_data_name' => 'name'));
      $view->destroy();
    }
  }

}
