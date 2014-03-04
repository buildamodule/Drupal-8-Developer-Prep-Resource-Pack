<?php

/**
 * @file
 * Definition of Drupal\views\Tests\DefaultViewsTest.
 */

namespace Drupal\views\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;
use Drupal\views\ViewExecutable;

/**
 * Tests for views default views.
 */
class DefaultViewsTest extends ViewTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('views', 'node', 'search', 'comment', 'taxonomy', 'block');

  /**
   * An array of argument arrays to use for default views.
   *
   * @var array
   */
  protected $viewArgMap = array(
    'backlinks' => array(1),
    'taxonomy_term' => array(1),
    'glossary' => array('all'),
  );

  public static function getInfo() {
    return array(
      'name' => 'Default views',
      'description' => 'Tests the default views provided by views',
      'group' => 'Views',
    );
  }

  protected function setUp() {
    parent::setUp();

    // Create Basic page node type.
    $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));

    $this->vocabulary = entity_create('taxonomy_vocabulary', array(
      'name' => $this->randomName(),
      'description' => $this->randomName(),
      'vid' => drupal_strtolower($this->randomName()),
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
      'help' => '',
      'nodes' => array('page' => 'page'),
      'weight' => mt_rand(0, 10),
    ));
    $this->vocabulary->save();

    // Setup a field and instance.
    $this->field_name = drupal_strtolower($this->randomName());
    entity_create('field_entity', array(
      'field_name' => $this->field_name,
      'type' => 'taxonomy_term_reference',
      'settings' => array(
        'allowed_values' => array(
          array(
            'vocabulary' => $this->vocabulary->id(),
            'parent' => '0',
          ),
        ),
      )
    ))->save();
    entity_create('field_instance', array(
      'field_name' => $this->field_name,
      'entity_type' => 'node',
      'bundle' => 'page',
    ))->save();

    // Create a time in the past for the archive.
    $time = REQUEST_TIME - 3600;

    for ($i = 0; $i <= 10; $i++) {
      $user = $this->drupalCreateUser();
      $term = $this->createTerm($this->vocabulary);

      $values = array('created' => $time, 'type' => 'page');
      $values[$this->field_name][]['target_id'] = $term->id();

      // Make every other node promoted.
      if ($i % 2) {
        $values['promote'] = TRUE;
      }
      $values['body'][]['value'] = l('Node ' . 1, 'node/' . 1);

      $node = $this->drupalCreateNode($values);

      search_index($node->id(), 'node', $node->body[Language::LANGCODE_NOT_SPECIFIED][0]['value'], Language::LANGCODE_NOT_SPECIFIED);

      $comment = array(
        'uid' => $user->id(),
        'nid' => $node->id(),
        'node_type' => 'node_type_' . $node->bundle(),
      );
      entity_create('comment', $comment)->save();
    }
  }

  /**
   * Test that all Default views work as expected.
   */
  public function testDefaultViews() {
    // Get all default views.
    $controller = $this->container->get('plugin.manager.entity')->getStorageController('view');
    $views = $controller->loadMultiple();

    foreach ($views as $name => $view_storage) {
      $view = $view_storage->getExecutable();
      $view->initDisplay();
      foreach ($view->storage->get('display') as $display_id => $display) {
        $view->setDisplay($display_id);

        // Add any args if needed.
        if (array_key_exists($name, $this->viewArgMap)) {
          $view->preExecute($this->viewArgMap[$name]);
        }

        $this->assert(TRUE, format_string('View @view will be executed.', array('@view' => $view->storage->id())));
        $view->execute();

        $tokens = array('@name' => $name, '@display_id' => $display_id);
        $this->assertTrue($view->executed, format_string('@name:@display_id has been executed.', $tokens));

        $count = count($view->result);
        $this->assertTrue($count > 0, format_string('@count results returned', array('@count' => $count)));
        $view->destroy();
      }
    }
  }

  /**
   * Returns a new term with random properties in vocabulary $vid.
   */
  function createTerm($vocabulary) {
    $filter_formats = filter_formats();
    $format = array_pop($filter_formats);
    $term = entity_create('taxonomy_term', array(
      'name' => $this->randomName(),
      'description' => $this->randomName(),
      // Use the first available text format.
      'format' => $format->format,
      'vid' => $vocabulary->id(),
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
    ));
    $term->save();
    return $term;
  }

  /**
   * Tests the archive view.
   */
  public function testArchiveView() {
    // Create additional nodes compared to the one in the setup method.
    // Create two nodes in the same month, and one in each following month.
    $node = array(
      'created' => 280299600, // Sun, 19 Nov 1978 05:00:00 GMT
    );
    $this->drupalCreateNode($node);
    $this->drupalCreateNode($node);
    $node = array(
      'created' => 282891600, // Tue, 19 Dec 1978 05:00:00 GMT
    );
    $this->drupalCreateNode($node);
    $node = array(
      'created' => 285570000, // Fri, 19 Jan 1979 05:00:00 GMT
    );
    $this->drupalCreateNode($node);

    $view = views_get_view('archive');
    $view->setDisplay('page_1');
    $this->executeView($view);
    $column_map = drupal_map_assoc(array('nid', 'created_year_month', 'num_records'));
    // Create time of additional nodes created in the setup method.
    $created_year_month = date('Ym', REQUEST_TIME - 3600);
    $expected_result = array(
      array(
        'nid' => 1,
        'created_year_month' => $created_year_month,
        'num_records' => 11,
      ),
      array(
        'nid' => 15,
        'created_year_month' => 197901,
        'num_records' => 1,
      ),
      array(
        'nid' => 14,
        'created_year_month' => 197812,
        'num_records' => 1,
      ),
      array(
        'nid' => 12,
        'created_year_month' => 197811,
        'num_records' => 2,
      ),
    );
    $this->assertIdenticalResultset($view, $expected_result, $column_map);
  }

}
