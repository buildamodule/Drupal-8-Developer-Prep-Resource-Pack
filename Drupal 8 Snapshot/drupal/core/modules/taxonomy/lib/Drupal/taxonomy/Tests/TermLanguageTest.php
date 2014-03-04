<?php

/**
 * @file
 * Definition of Drupal\taxonomy\Tests\TermLanguageTest.
 */

namespace Drupal\taxonomy\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests for the language feature on taxonomy terms.
 */
class TermLanguageTest extends TaxonomyTestBase {

  public static $modules = array('language');

  public static function getInfo() {
    return array(
      'name' => 'Taxonomy term language',
      'description' => 'Tests the language functionality for the taxonomy terms.',
      'group' => 'Taxonomy',
    );
  }

  function setUp() {
    parent::setUp();

    // Create an administrative user.
    $this->admin_user = $this->drupalCreateUser(array('administer taxonomy'));
    $this->drupalLogin($this->admin_user);

    // Create a vocabulary to which the terms will be assigned.
    $this->vocabulary = $this->createVocabulary();

    // Add some custom languages.
    foreach (array('aa', 'bb', 'cc') as $language_code) {
      $language = new Language(array(
        'id' => $language_code,
        'name' => $this->randomName(),
      ));
      language_save($language);
    }
  }

  function testTermLanguage() {
    // Configure the vocabulary to not hide the language selector.
    $edit = array(
      'default_language[language_show]' => TRUE,
    );
    $this->drupalPost('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/edit', $edit, t('Save'));

    // Add a term.
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    // Check that we have the language selector.
    $this->assertField('edit-langcode', t('The language selector field was found on the page'));
    // Submit the term.
    $edit = array(
      'name' => $this->randomName(),
      'langcode' => 'aa',
    );
    $this->drupalPost(NULL, $edit, t('Save'));
    $terms = taxonomy_term_load_multiple_by_name($edit['name']);
    $term = reset($terms);
    $this->assertEqual($term->language()->id, $edit['langcode']);

    // Check if on the edit page the language is correct.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $this->assertOptionSelected('edit-langcode', $edit['langcode'], 'The term language was correctly selected.');

    // Change the language of the term.
    $edit['langcode'] = 'bb';
    $this->drupalPost('taxonomy/term/' . $term->id() . '/edit', $edit, t('Save'));

    // Check again that on the edit page the language is correct.
    $this->drupalGet('taxonomy/term/' . $term->id() . '/edit');
    $this->assertOptionSelected('edit-langcode', $edit['langcode'], 'The term language was correctly selected.');
  }

  function testDefaultTermLanguage() {
    // Configure the vocabulary to not hide the language selector, and make the
    // default language of the terms fixed.
    $edit = array(
      'default_language[langcode]' => 'bb',
      'default_language[language_show]' => TRUE,
    );
    $this->drupalPost('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/edit', $edit, t('Save'));
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    $this->assertOptionSelected('edit-langcode', 'bb');

    // Make the default language of the terms to be the current interface.
    $edit = array(
      'default_language[langcode]' => 'current_interface',
      'default_language[language_show]' => TRUE,
    );
    $this->drupalPost('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/edit', $edit, t('Save'));
    $this->drupalGet('aa/admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    $this->assertOptionSelected('edit-langcode', 'aa');
    $this->drupalGet('bb/admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    $this->assertOptionSelected('edit-langcode', 'bb');

    // Change the default language of the site and check if the default terms
    // language is still correctly selected.
    $old_default = language_default();
    $old_default->default = FALSE;
    language_save($old_default);
    $new_default = language_load('cc');
    $new_default->default = TRUE;
    language_save($new_default);
    $edit = array(
      'default_language[langcode]' => 'site_default',
      'default_language[language_show]' => TRUE,
    );
    $this->drupalPost('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/edit', $edit, t('Save'));
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/add');
    $this->assertOptionSelected('edit-langcode', 'cc');
  }
}
