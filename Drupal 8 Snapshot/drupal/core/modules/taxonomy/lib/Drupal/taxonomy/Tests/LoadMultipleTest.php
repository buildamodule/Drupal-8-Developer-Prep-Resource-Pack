<?php

/**
 * @file
 * Definition of Drupal\taxonomy\Tests\LoadMultipleTest.
 */

namespace Drupal\taxonomy\Tests;

/**
 * Test the entity_load_multiple() function.
 */
class LoadMultipleTest extends TaxonomyTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Taxonomy term multiple loading',
      'description' => 'Test the loading of multiple taxonomy terms at once',
      'group' => 'Taxonomy',
    );
  }

  function setUp() {
    parent::setUp();
    $this->taxonomy_admin = $this->drupalCreateUser(array('administer taxonomy'));
    $this->drupalLogin($this->taxonomy_admin);
  }

  /**
   * Create a vocabulary and some taxonomy terms, ensuring they're loaded
   * correctly using entity_load_multiple().
   */
  function testTaxonomyTermMultipleLoad() {
    // Create a vocabulary.
    $vocabulary = $this->createVocabulary();

    // Create five terms in the vocabulary.
    $i = 0;
    while ($i < 5) {
      $i++;
      $this->createTerm($vocabulary);
    }
    // Load the terms from the vocabulary.
    $terms = entity_load_multiple_by_properties('taxonomy_term', array('vid' => $vocabulary->id()));
    $count = count($terms);
    $this->assertEqual($count, 5, format_string('Correct number of terms were loaded. !count terms.', array('!count' => $count)));

    // Load the same terms again by tid.
    $terms2 = entity_load_multiple('taxonomy_term', array_keys($terms));
    $this->assertEqual($count, count($terms2), 'Five terms were loaded by tid.');
    $this->assertEqual($terms, $terms2, 'Both arrays contain the same terms.');

    // Remove one term from the array, then delete it.
    $deleted = array_shift($terms2);
    $deleted->delete();
    $deleted_term = entity_load('taxonomy_term', $deleted->id());
    $this->assertFalse($deleted_term);

    // Load terms from the vocabulary by vid.
    $terms3 = entity_load_multiple_by_properties('taxonomy_term', array('vid' => $vocabulary->id()));
    $this->assertEqual(count($terms3), 4, 'Correct number of terms were loaded.');
    $this->assertFalse(isset($terms3[$deleted->id()]));

    // Create a single term and load it by name.
    $term = $this->createTerm($vocabulary);
    $loaded_terms = entity_load_multiple_by_properties('taxonomy_term', array('name' => $term->name->value));
    $this->assertEqual(count($loaded_terms), 1, 'One term was loaded.');
    $loaded_term = reset($loaded_terms);
    $this->assertEqual($term->id(), $loaded_term->id(), 'Term loaded by name successfully.');
  }
}
