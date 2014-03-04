<?php

/**
 * @file
 * Definition of Drupal\taxonomy\Tests\TermTest.
 */

namespace Drupal\taxonomy\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests for taxonomy term functions.
 */
class TermTest extends TaxonomyTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Taxonomy term functions and forms',
      'description' => 'Test load, save and delete for taxonomy terms.',
      'group' => 'Taxonomy',
    );
  }

  function setUp() {
    parent::setUp();
    $this->admin_user = $this->drupalCreateUser(array('administer taxonomy', 'bypass node access'));
    $this->drupalLogin($this->admin_user);
    $this->vocabulary = $this->createVocabulary();

    $field = array(
      'field_name' => 'taxonomy_' . $this->vocabulary->id(),
      'type' => 'taxonomy_term_reference',
      'cardinality' => FIELD_CARDINALITY_UNLIMITED,
      'settings' => array(
        'allowed_values' => array(
          array(
            'vocabulary' => $this->vocabulary->id(),
            'parent' => 0,
          ),
        ),
      ),
    );
    entity_create('field_entity', $field)->save();

    $this->instance = entity_create('field_instance', array(
      'field_name' => 'taxonomy_' . $this->vocabulary->id(),
      'bundle' => 'article',
      'entity_type' => 'node',
    ));
    $this->instance->save();
    entity_get_form_display('node', 'article', 'default')
      ->setComponent('taxonomy_' . $this->vocabulary->id(), array(
        'type' => 'options_select',
      ))
      ->save();
    entity_get_display('node', 'article', 'default')
      ->setComponent($this->instance['field_name'], array(
        'type' => 'taxonomy_term_reference_link',
      ))
      ->save();
  }

  /**
   * Test terms in a single and multiple hierarchy.
   */
  function testTaxonomyTermHierarchy() {
    // Create two taxonomy terms.
    $term1 = $this->createTerm($this->vocabulary);
    $term2 = $this->createTerm($this->vocabulary);

    // Check that hierarchy is flat.
    $vocabulary = entity_load('taxonomy_vocabulary', $this->vocabulary->id());
    $this->assertEqual(0, $vocabulary->hierarchy, 'Vocabulary is flat.');

    // Edit $term2, setting $term1 as parent.
    $edit = array();
    $edit['parent[]'] = array($term1->id());
    $this->drupalPost('taxonomy/term/' . $term2->id() . '/edit', $edit, t('Save'));

    // Check the hierarchy.
    $children = taxonomy_term_load_children($term1->id());
    $parents = taxonomy_term_load_parents($term2->id());
    $this->assertTrue(isset($children[$term2->id()]), 'Child found correctly.');
    $this->assertTrue(isset($parents[$term1->id()]), 'Parent found correctly.');

    // Load and save a term, confirming that parents are still set.
    $term = entity_load('taxonomy_term', $term2->id());
    $term->save();
    $parents = taxonomy_term_load_parents($term2->id());
    $this->assertTrue(isset($parents[$term1->id()]), 'Parent found correctly.');

    // Create a third term and save this as a parent of term2.
    $term3 = $this->createTerm($this->vocabulary);
    $term2->parent = array($term1->id(), $term3->id());
    $term2->save();
    $parents = taxonomy_term_load_parents($term2->id());
    $this->assertTrue(isset($parents[$term1->id()]) && isset($parents[$term3->id()]), 'Both parents found successfully.');
  }

  /**
   * Test that hook_node_$op implementations work correctly.
   *
   * Save & edit a node and assert that taxonomy terms are saved/loaded properly.
   */
  function testTaxonomyNode() {
    // Create two taxonomy terms.
    $term1 = $this->createTerm($this->vocabulary);
    $term2 = $this->createTerm($this->vocabulary);

    // Post an article.
    $edit = array();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $edit["title"] = $this->randomName();
    $edit["body[$langcode][0][value]"] = $this->randomName();
    $edit[$this->instance['field_name'] . '[' . $langcode . '][]'] = $term1->id();
    $this->drupalPost('node/add/article', $edit, t('Save'));

    // Check that the term is displayed when the node is viewed.
    $node = $this->drupalGetNodeByTitle($edit["title"]);
    $this->drupalGet('node/' . $node->id());
    $this->assertText($term1->label(), 'Term is displayed when viewing the node.');

    $this->clickLink(t('Edit'));
    $this->assertText($term1->label(), 'Term is displayed when editing the node.');
    $this->drupalPost(NULL, array(), t('Save'));
    $this->assertText($term1->label(), 'Term is displayed after saving the node with no changes.');

    // Edit the node with a different term.
    $edit[$this->instance['field_name'] . '[' . $langcode . '][]'] = $term2->id();
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save'));

    $this->drupalGet('node/' . $node->id());
    $this->assertText($term2->label(), 'Term is displayed when viewing the node.');

    // Preview the node.
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Preview'));
    $this->assertNoUniqueText($term2->label(), 'Term is displayed when previewing the node.');
    $this->drupalPost(NULL, NULL, t('Preview'));
    $this->assertNoUniqueText($term2->label(), 'Term is displayed when previewing the node again.');
  }

  /**
   * Test term creation with a free-tagging vocabulary from the node form.
   */
  function testNodeTermCreationAndDeletion() {
    // Enable tags in the vocabulary.
    $instance = $this->instance;
    entity_get_form_display($instance['entity_type'], $instance['bundle'], 'default')
      ->setComponent($instance['field_name'], array(
        'type' => 'taxonomy_autocomplete',
        'settings' => array(
          'placeholder' => 'Start typing here.',
        ),
      ))
      ->save();
    $terms = array(
      'term1' => $this->randomName(),
      'term2' => $this->randomName(),
      'term3' => $this->randomName() . ', ' . $this->randomName(),
      'term4' => $this->randomName(),
    );

    $edit = array();
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $edit["title"] = $this->randomName();
    $edit["body[$langcode][0][value]"] = $this->randomName();
    // Insert the terms in a comma separated list. Vocabulary 1 is a
    // free-tagging field created by the default profile.
    $edit[$instance['field_name'] . "[$langcode]"] = drupal_implode_tags($terms);

    // Verify the placeholder is there.
    $this->drupalGet('node/add/article');
    $this->assertRaw('placeholder="Start typing here."');

    // Preview and verify the terms appear but are not created.
    $this->drupalPost(NULL, $edit, t('Preview'));
    foreach ($terms as $term) {
      $this->assertText($term, 'The term appears on the node preview');
    }
    $tree = taxonomy_get_tree($this->vocabulary->id());
    $this->assertTrue(empty($tree), 'The terms are not created on preview.');

    // taxonomy.module does not maintain its static caches.
    taxonomy_terms_static_reset();

    // Save, creating the terms.
    $this->drupalPost('node/add/article', $edit, t('Save'));
    $this->assertRaw(t('@type %title has been created.', array('@type' => t('Article'), '%title' => $edit["title"])), 'The node was created successfully.');
    foreach ($terms as $term) {
      $this->assertText($term, 'The term was saved and appears on the node page.');
    }

    // Get the created terms.
    $term_objects = array();
    foreach ($terms as $key => $term) {
      $term_objects[$key] = taxonomy_term_load_multiple_by_name($term);
      $term_objects[$key] = reset($term_objects[$key]);
    }

    // Delete term 1 from the term edit page.
    $this->drupalPost('taxonomy/term/' . $term_objects['term1']->id() . '/edit', array(), t('Delete'));
    $this->drupalPost(NULL, NULL, t('Delete'));

    // Delete term 2 from the term delete page.
    $this->drupalPost('taxonomy/term/' . $term_objects['term2']->id() . '/delete', array(), t('Delete'));
    $term_names = array($term_objects['term3']->label(), $term_objects['term4']->label());

    // Get the node.
    $node = $this->drupalGetNodeByTitle($edit["title"]);
    $this->drupalGet('node/' . $node->id());

    foreach ($term_names as $term_name) {
      $this->assertText($term_name, format_string('The term %name appears on the node page after two terms, %deleted1 and %deleted2, were deleted', array('%name' => $term_name, '%deleted1' => $term_objects['term1']->label(), '%deleted2' => $term_objects['term2']->label())));
    }
    $this->assertNoText($term_objects['term1']->label(), format_string('The deleted term %name does not appear on the node page.', array('%name' => $term_objects['term1']->label())));
    $this->assertNoText($term_objects['term2']->label(), format_string('The deleted term %name does not appear on the node page.', array('%name' => $term_objects['term2']->label())));

    // Test autocomplete on term 3, which contains a comma.
    // The term will be quoted, and the " will be encoded in unicode (\u0022).
    $input = substr($term_objects['term3']->label(), 0, 3);
    $json = $this->drupalGet('taxonomy/autocomplete/taxonomy_' . $this->vocabulary->id(), array('query' => array('q' => $input)));
    $this->assertEqual($json, '{"\u0022' . $term_objects['term3']->label() . '\u0022":"' . $term_objects['term3']->label() . '"}', format_string('Autocomplete returns term %term_name after typing the first 3 letters.', array('%term_name' => $term_objects['term3']->label())));

    // Test autocomplete on term 4 - it is alphanumeric only, so no extra
    // quoting.
    $input = substr($term_objects['term4']->label(), 0, 3);
    $this->drupalGet('taxonomy/autocomplete/taxonomy_' . $this->vocabulary->id(), array('query' => array('q' => $input)));
    $this->assertRaw('{"' . $term_objects['term4']->label() . '":"' . $term_objects['term4']->label() . '"}', format_string('Autocomplete returns term %term_name after typing the first 3 letters.', array('%term_name' => $term_objects['term4']->label())));

    // Test taxonomy autocomplete with a nonexistent field.
    $field_name = $this->randomName();
    $tag = $this->randomName();
    $message = t("Taxonomy field @field_name not found.", array('@field_name' => $field_name));
    $this->assertFalse(field_info_field($field_name), format_string('Field %field_name does not exist.', array('%field_name' => $field_name)));
    $this->drupalGet('taxonomy/autocomplete/' . $field_name, array('query' => array('q' => $tag)));
    $this->assertRaw($message, 'Autocomplete returns correct error message when the taxonomy field does not exist.');
  }

  /**
   * Tests term autocompletion edge cases with slashes in the names.
   */
  function testTermAutocompletion() {
    // Add a term with a slash in the name.
    $first_term = $this->createTerm($this->vocabulary);
    $first_term->name = '10/16/2011';
    $first_term->save();
    // Add another term that differs after the slash character.
    $second_term = $this->createTerm($this->vocabulary);
    $second_term->name = '10/17/2011';
    $second_term->save();
    // Add another term that has both a comma and a slash character.
    $third_term = $this->createTerm($this->vocabulary);
    $third_term->name = 'term with, a comma and / a slash';
    $third_term->save();

    // Try to autocomplete a term name that matches both terms.
    // We should get both term in a json encoded string.
    $input = '10/';
    $path = 'taxonomy/autocomplete/taxonomy_' . $this->vocabulary->id();
    // The result order is not guaranteed, so check each term separately.
    $result = $this->drupalGet($path, array('query' => array('q' => $input)));
    $data = drupal_json_decode($result);
    $this->assertEqual($data[$first_term->label()], check_plain($first_term->label()), 'Autocomplete returned the first matching term');
    $this->assertEqual($data[$second_term->label()], check_plain($second_term->label()), 'Autocomplete returned the second matching term');

    // Try to autocomplete a term name that matches first term.
    // We should only get the first term in a json encoded string.
    $input = '10/16';
    $path = 'taxonomy/autocomplete/taxonomy_' . $this->vocabulary->id();
    $this->drupalGet($path, array('query' => array('q' => $input)));
    $target = array($first_term->label() => check_plain($first_term->label()));
    $this->assertRaw(drupal_json_encode($target), 'Autocomplete returns only the expected matching term.');

    // Try to autocomplete a term name with both a comma and a slash.
    $input = '"term with, comma and / a';
    $path = 'taxonomy/autocomplete/taxonomy_' . $this->vocabulary->id();
    $this->drupalGet($path, array('query' => array('q' => $input)));
    $n = $third_term->label();
    // Term names containing commas or quotes must be wrapped in quotes.
    if (strpos($third_term->label(), ',') !== FALSE || strpos($third_term->label(), '"') !== FALSE) {
      $n = '"' . str_replace('"', '""', $third_term->label()) . '"';
    }
    $target = array($n => check_plain($third_term->label()));
    $this->assertRaw(drupal_json_encode($target), 'Autocomplete returns a term containing a comma and a slash.');
  }

  /**
   * Save, edit and delete a term using the user interface.
   */
  function testTermInterface() {
    $edit = array(
      'name' => $this->randomName(12),
      'description[value]' => $this->randomName(100),
    );
    // Explicitly set the parents field to 'root', to ensure that
    // TermFormController::save() handles the invalid term ID correctly.
    $edit['parent[]'] = array(0);

    // Create the term to edit.
    $this->drupalPost('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add', $edit, t('Save'));

    $terms = taxonomy_term_load_multiple_by_name($edit['name']);
    $term = reset($terms);
    $this->assertNotNull($term, 'Term found in database.');

    // Submitting a term takes us to the add page; we need the List page.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id());

    // Test edit link as accessed from Taxonomy administration pages.
    // Because Simpletest creates its own database when running tests, we know
    // the first edit link found on the listing page is to our term.
    $this->clickLink(t('edit'));

    $this->assertRaw($edit['name'], 'The randomly generated term name is present.');
    $this->assertText($edit['description[value]'], 'The randomly generated term description is present.');

    $edit = array(
      'name' => $this->randomName(14),
      'description[value]' => $this->randomName(102),
    );

    // Edit the term.
    $this->drupalPost('taxonomy/term/' . $term->id() . '/edit', $edit, t('Save'));

    // Check that the term is still present at admin UI after edit.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id());
    $this->assertText($edit['name'], 'The randomly generated term name is present.');
    $this->assertLink(t('edit'));

    // Check the term link can be clicked through to the term page.
    $this->clickLink($edit['name']);
    $this->assertResponse(200, 'Term page can be accessed via the listing link.');

    // View the term and check that it is correct.
    $this->drupalGet('taxonomy/term/' . $term->id());
    $this->assertText($edit['name'], 'The randomly generated term name is present.');
    $this->assertText($edit['description[value]'], 'The randomly generated term description is present.');

    // Did this page request display a 'term-listing-heading'?
    $this->assertPattern('|class="taxonomy-term-description"|', 'Term page displayed the term description element.');
    // Check that it does NOT show a description when description is blank.
    $term->description = '';
    $term->save();
    $this->drupalGet('taxonomy/term/' . $term->id());
    $this->assertNoPattern('|class="taxonomy-term-description"|', 'Term page did not display the term description when description was blank.');

    // Check that the term feed page is working.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/feed');

    // Check that the term edit page does not try to interpret additional path
    // components as arguments for entity_get_form().
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit/' . $this->randomName());
    $this->assertResponse(200, 'The taxonomy term edit menu item ensured appropriate arguments were passed to its page callback.');

    // Delete the term.
    $this->drupalPost('taxonomy/term/' . $term->id() . '/edit', array(), t('Delete'));
    $this->drupalPost(NULL, NULL, t('Delete'));

    // Assert that the term no longer exists.
    $this->drupalGet('taxonomy/term/' . $term->id());
    $this->assertResponse(404, 'The taxonomy term page was not found.');
  }

  /**
   * Save, edit and delete a term using the user interface.
   */
  function testTermReorder() {
    $this->createTerm($this->vocabulary);
    $this->createTerm($this->vocabulary);
    $this->createTerm($this->vocabulary);

    // Fetch the created terms in the default alphabetical order, i.e. term1
    // precedes term2 alphabetically, and term2 precedes term3.
    drupal_static_reset('taxonomy_get_tree');
    drupal_static_reset('taxonomy_get_treeparent');
    drupal_static_reset('taxonomy_get_treeterms');
    list($term1, $term2, $term3) = taxonomy_get_tree($this->vocabulary->id(), 0, NULL, TRUE);

    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id());

    // Each term has four hidden fields, "tid:1:0[tid]", "tid:1:0[parent]",
    // "tid:1:0[depth]", and "tid:1:0[weight]". Change the order to term2,
    // term3, term1 by setting weight property, make term3 a child of term2 by
    // setting the parent and depth properties, and update all hidden fields.
    $edit = array(
      'terms[tid:' . $term2->id() . ':0][term][tid]' => $term2->id(),
      'terms[tid:' . $term2->id() . ':0][term][parent]' => 0,
      'terms[tid:' . $term2->id() . ':0][term][depth]' => 0,
      'terms[tid:' . $term2->id() . ':0][weight]' => 0,
      'terms[tid:' . $term3->id() . ':0][term][tid]' => $term3->id(),
      'terms[tid:' . $term3->id() . ':0][term][parent]' => $term2->id(),
      'terms[tid:' . $term3->id() . ':0][term][depth]' => 1,
      'terms[tid:' . $term3->id() . ':0][weight]' => 1,
      'terms[tid:' . $term1->id() . ':0][term][tid]' => $term1->id(),
      'terms[tid:' . $term1->id() . ':0][term][parent]' => 0,
      'terms[tid:' . $term1->id() . ':0][term][depth]' => 0,
      'terms[tid:' . $term1->id() . ':0][weight]' => 2,
    );
    $this->drupalPost(NULL, $edit, t('Save'));

    drupal_static_reset('taxonomy_get_tree');
    drupal_static_reset('taxonomy_get_treeparent');
    drupal_static_reset('taxonomy_get_treeterms');
    $terms = taxonomy_get_tree($this->vocabulary->id());
    $this->assertEqual($terms[0]->tid, $term2->id(), 'Term 2 was moved above term 1.');
    $this->assertEqual($terms[1]->parents, array($term2->id()), 'Term 3 was made a child of term 2.');
    $this->assertEqual($terms[2]->tid, $term1->id(), 'Term 1 was moved below term 2.');

    $this->drupalPost('admin/structure/taxonomy/manage/' . $this->vocabulary->id(), array(), t('Reset to alphabetical'));
    // Submit confirmation form.
    $this->drupalPost(NULL, array(), t('Reset to alphabetical'));

    drupal_static_reset('taxonomy_get_tree');
    drupal_static_reset('taxonomy_get_treeparent');
    drupal_static_reset('taxonomy_get_treeterms');
    $terms = taxonomy_get_tree($this->vocabulary->id(), 0, NULL, TRUE);
    $this->assertEqual($terms[0]->id(), $term1->id(), 'Term 1 was moved to back above term 2.');
    $this->assertEqual($terms[1]->id(), $term2->id(), 'Term 2 was moved to back below term 1.');
    $this->assertEqual($terms[2]->id(), $term3->id(), 'Term 3 is still below term 2.');
    $this->assertEqual($terms[2]->parents, array($term2->id()), 'Term 3 is still a child of term 2.' . var_export($terms[1]->id(), 1));
  }

  /**
   * Test saving a term with multiple parents through the UI.
   */
  function testTermMultipleParentsInterface() {
    // Add a new term to the vocabulary so that we can have multiple parents.
    $parent = $this->createTerm($this->vocabulary);

    // Add a new term with multiple parents.
    $edit = array(
      'name' => $this->randomName(12),
      'description[value]' => $this->randomName(100),
      'parent[]' => array(0, $parent->id()),
    );
    // Save the new term.
    $this->drupalPost('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add', $edit, t('Save'));

    // Check that the term was successfully created.
    $terms = taxonomy_term_load_multiple_by_name($edit['name']);
    $term = reset($terms);
    $this->assertNotNull($term, 'Term found in database.');
    $this->assertEqual($edit['name'], $term->label(), 'Term name was successfully saved.');
    $this->assertEqual($edit['description[value]'], $term->description->value, 'Term description was successfully saved.');
    // Check that the parent tid is still there. The other parent (<root>) is
    // not added by taxonomy_term_load_parents().
    $parents = taxonomy_term_load_parents($term->id());
    $parent = reset($parents);
    $this->assertEqual($edit['parent[]'][1], $parent->id(), 'Term parents were successfully saved.');
  }

  /**
   * Test taxonomy_term_load_multiple_by_name().
   */
  function testTaxonomyGetTermByName() {
    $term = $this->createTerm($this->vocabulary);

    // Load the term with the exact name.
    $terms = taxonomy_term_load_multiple_by_name($term->label());
    $this->assertTrue(isset($terms[$term->id()]), 'Term loaded using exact name.');

    // Load the term with space concatenated.
    $terms = taxonomy_term_load_multiple_by_name('  ' . $term->label() . '   ');
    $this->assertTrue(isset($terms[$term->id()]), 'Term loaded with extra whitespace.');

    // Load the term with name uppercased.
    $terms = taxonomy_term_load_multiple_by_name(strtoupper($term->label()));
    $this->assertTrue(isset($terms[$term->id()]), 'Term loaded with uppercased name.');

    // Load the term with name lowercased.
    $terms = taxonomy_term_load_multiple_by_name(strtolower($term->label()));
    $this->assertTrue(isset($terms[$term->id()]), 'Term loaded with lowercased name.');

    // Try to load an invalid term name.
    $terms = taxonomy_term_load_multiple_by_name('Banana');
    $this->assertFalse($terms);

    // Try to load the term using a substring of the name.
    $terms = taxonomy_term_load_multiple_by_name(drupal_substr($term->label(), 2));
    $this->assertFalse($terms);

    // Create a new term in a different vocabulary with the same name.
    $new_vocabulary = $this->createVocabulary();
    $new_term = entity_create('taxonomy_term', array(
      'name' => $term->label(),
      'vid' => $new_vocabulary->id(),
    ));
    $new_term->save();

    // Load multiple terms with the same name.
    $terms = taxonomy_term_load_multiple_by_name($term->label());
    $this->assertEqual(count($terms), 2, 'Two terms loaded with the same name.');

    // Load single term when restricted to one vocabulary.
    $terms = taxonomy_term_load_multiple_by_name($term->label(), $this->vocabulary->id());
    $this->assertEqual(count($terms), 1, 'One term loaded when restricted by vocabulary.');
    $this->assertTrue(isset($terms[$term->id()]), 'Term loaded using exact name and vocabulary machine name.');

    // Create a new term with another name.
    $term2 = $this->createTerm($this->vocabulary);

    // Try to load a term by name that doesn't exist in this vocabulary but
    // exists in another vocabulary.
    $terms = taxonomy_term_load_multiple_by_name($term2->label(), $new_vocabulary->id());
    $this->assertFalse($terms, 'Invalid term name restricted by vocabulary machine name not loaded.');

    // Try to load terms filtering by a non-existing vocabulary.
    $terms = taxonomy_term_load_multiple_by_name($term2->label(), 'non_existing_vocabulary');
    $this->assertEqual(count($terms), 0, 'No terms loaded when restricted by a non-existing vocabulary.');
  }

  /**
   * Tests that editing and saving a node with no changes works correctly.
   */
  function testReSavingTags() {
    // Enable tags in the vocabulary.
    $instance = $this->instance;
    entity_get_form_display($instance['entity_type'], $instance['bundle'], 'default')
      ->setComponent($instance['field_name'], array(
        'type' => 'taxonomy_autocomplete',
      ))
      ->save();

    // Create a term and a node using it.
    $term = $this->createTerm($this->vocabulary);
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $edit = array();
    $edit["title"] = $this->randomName(8);
    $edit["body[$langcode][0][value]"] = $this->randomName(16);
    $edit[$this->instance['field_name'] . '[' . $langcode . ']'] = $term->label();
    $this->drupalPost('node/add/article', $edit, t('Save'));

    // Check that the term is displayed when editing and saving the node with no
    // changes.
    $this->clickLink(t('Edit'));
    $this->assertRaw($term->label(), 'Term is displayed when editing the node.');
    $this->drupalPost(NULL, array(), t('Save'));
    $this->assertRaw($term->label(), 'Term is displayed after saving the node with no changes.');
  }

}
