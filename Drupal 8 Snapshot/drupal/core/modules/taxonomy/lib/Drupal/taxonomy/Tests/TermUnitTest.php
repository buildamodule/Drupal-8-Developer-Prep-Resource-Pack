<?php

/**
 * @file
 * Definition of Drupal\taxonomy\Tests\TermUnitTest.
 */

namespace Drupal\taxonomy\Tests;

/**
 * Unit tests for taxonomy term functions.
 */
class TermUnitTest extends TaxonomyTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Taxonomy term unit tests',
      'description' => 'Unit tests for taxonomy term functions.',
      'group' => 'Taxonomy',
    );
  }

  function testTermDelete() {
    $vocabulary = $this->createVocabulary();
    $valid_term = $this->createTerm($vocabulary);
    // Delete a valid term.
    $valid_term->delete();
    $terms = entity_load_multiple_by_properties('taxonomy_term', array('vid' => $vocabulary->id()));
    $this->assertTrue(empty($terms), 'Vocabulary is empty after deletion');

    // Delete an invalid term. Should not throw any notices.
    entity_delete_multiple('taxonomy_term', array(42));
  }

  /**
   * Test a taxonomy with terms that have multiple parents of different depths.
   */
  function testTaxonomyVocabularyTree() {
    // Create a new vocabulary with 6 terms.
    $vocabulary = $this->createVocabulary();
    $term = array();
    for ($i = 0; $i < 6; $i++) {
      $term[$i] = $this->createTerm($vocabulary);
    }

    // $term[2] is a child of 1 and 5.
    $term[2]->parent = array($term[1]->id(), $term[5]->id());
    $term[2]->save();
    // $term[3] is a child of 2.
    $term[3]->parent = array($term[2]->id());
    $term[3]->save();
    // $term[5] is a child of 4.
    $term[5]->parent = array($term[4]->id());
    $term[5]->save();

    /**
     * Expected tree:
     * term[0] | depth: 0
     * term[1] | depth: 0
     * -- term[2] | depth: 1
     * ---- term[3] | depth: 2
     * term[4] | depth: 0
     * -- term[5] | depth: 1
     * ---- term[2] | depth: 2
     * ------ term[3] | depth: 3
     */
    // Count $term[1] parents with $max_depth = 1.
    $tree = taxonomy_get_tree($vocabulary->id(), $term[1]->id(), 1);
    $this->assertEqual(1, count($tree), 'We have one parent with depth 1.');

    // Count all vocabulary tree elements.
    $tree = taxonomy_get_tree($vocabulary->id());
    $this->assertEqual(8, count($tree), 'We have all vocabulary tree elements.');

    // Count elements in every tree depth.
    foreach ($tree as $element) {
      if (!isset($depth_count[$element->depth])) {
        $depth_count[$element->depth] = 0;
      }
      $depth_count[$element->depth]++;
    }
    $this->assertEqual(3, $depth_count[0], 'Three elements in taxonomy tree depth 0.');
    $this->assertEqual(2, $depth_count[1], 'Two elements in taxonomy tree depth 1.');
    $this->assertEqual(2, $depth_count[2], 'Two elements in taxonomy tree depth 2.');
    $this->assertEqual(1, $depth_count[3], 'One element in taxonomy tree depth 3.');
  }
}
