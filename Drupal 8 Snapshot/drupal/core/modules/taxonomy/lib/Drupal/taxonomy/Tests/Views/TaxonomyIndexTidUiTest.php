<?php

/**
 * @file
 * Contains \Drupal\taxonomy\Tests\Views\TaxonomyIndexTidUiTest.
 */

namespace Drupal\taxonomy\Tests\Views;

use Drupal\views\Tests\ViewTestData;
use Drupal\views_ui\Tests\UITestBase;

/**
 * Tests the taxonomy index filter handler UI.
 *
 * @see \Drupal\taxonomy\Plugin\views\field\TaxonomyIndexTid
 */
class TaxonomyIndexTidUiTest extends UITestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_filter_taxonomy_index_tid');

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'taxonomy', 'taxonomy_test_views');

  public static function getInfo() {
    return array(
      'name' => 'Taxonomy: node index Filter (UI)',
      'description' => 'Tests the taxonomy index filter handler UI.',
      'group' => 'Views module integration',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    ViewTestData::importTestViews(get_class($this), array('taxonomy_test_views'));
  }

  /**
   * Tests the filter UI.
   */
  public function testFilterUI() {
    entity_create('taxonomy_vocabulary', array(
      'vid' => 'tags',
      'name' => 'Tags',
    ))->save();

    $terms = array();
    // Setup a hierarchy which looks like this:
    // term 0.0
    // term 1.0
    // - term 1.1
    // term 2.0
    // - term 2.1
    // - term 2.2
    for ($i = 0; $i < 3; $i++) {
      for ($j = 0; $j <= $i; $j++) {
        $terms[$i][$j] = $term = entity_create('taxonomy_term', array(
          'vid' => 'tags',
          'name' => "Term $i.$j",
          'parent' => isset($terms[$i][0]) ? $terms[$i][0]->id() : 0,
        ));
        $term->save();
      }
    }

    $this->drupalGet('admin/structure/views/nojs/config-item/test_filter_taxonomy_index_tid/default/filter/tid');

    $result = $this->xpath('//select[@id="edit-options-value"]/option');

    // Ensure that the expected hierarchy is available in the UI.
    $counter = 0;
    for ($i = 0; $i < 3; $i++) {
      for ($j = 0; $j <= $i; $j++) {
        $option = $result[$counter++];
        $prefix = $terms[$i][$j]->parent->value ? '-' : '';
        $attributes = $option->attributes();
        $tid = (string) $attributes->value;

        $this->assertEqual($prefix . $terms[$i][$j]->label(), (string) $option);
        $this->assertEqual($terms[$i][$j]->id(), $tid);
      }
    }
  }

}
