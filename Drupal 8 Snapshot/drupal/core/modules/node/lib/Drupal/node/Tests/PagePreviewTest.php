<?php

/**
 * @file
 * Definition of Drupal\node\Tests\PagePreviewTest.
 */

namespace Drupal\node\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests the node entity preview functionality.
 */
class PagePreviewTest extends NodeTestBase {

  /**
   * Enable the node and taxonomy modules to test both on the preview.
   *
   * @var array
   */
  public static $modules = array('node', 'taxonomy');

  /**
   * The name of the created field.
   *
   * @var string
   */
  protected $field_name;

  public static function getInfo() {
    return array(
      'name' => 'Node preview',
      'description' => 'Test node preview functionality.',
      'group' => 'Node',
    );
  }

  function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser(array('edit own page content', 'create page content'));
    $this->drupalLogin($web_user);

    // Add a vocabulary so we can test different view modes.
    $vocabulary = entity_create('taxonomy_vocabulary', array(
      'name' => $this->randomName(),
      'description' => $this->randomName(),
      'vid' => $this->randomName(),
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
      'help' => '',
    ));
    $vocabulary->save();

    $this->vocabulary = $vocabulary;

    // Add a term to the vocabulary.
    $term = entity_create('taxonomy_term', array(
      'name' => $this->randomName(),
      'description' => $this->randomName(),
      'vid' => $this->vocabulary->id(),
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
    ));
    $term->save();

    $this->term = $term;

    // Set up a field and instance.
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
      ),
      'cardinality' => '-1',
    ))->save();
    entity_create('field_instance', array(
      'field_name' => $this->field_name,
      'entity_type' => 'node',
      'bundle' => 'page',
    ))->save();

    entity_get_form_display('node', 'page', 'default')
      ->setComponent($this->field_name, array(
        'type' => 'taxonomy_autocomplete',
      ))
      ->save();

    // Show on default display and teaser.
    entity_get_display('node', 'page', 'default')
      ->setComponent($this->field_name, array(
        'type' => 'taxonomy_term_reference_link',
      ))
      ->save();
    entity_get_display('node', 'page', 'teaser')
      ->setComponent($this->field_name, array(
        'type' => 'taxonomy_term_reference_link',
      ))
      ->save();
  }

  /**
   * Checks the node preview functionality.
   */
  function testPagePreview() {
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $title_key = "title";
    $body_key = "body[$langcode][0][value]";
    $term_key = "{$this->field_name}[$langcode]";

    // Fill in node creation form and preview node.
    $edit = array();
    $edit[$title_key] = $this->randomName(8);
    $edit[$body_key] = $this->randomName(16);
    $edit[$term_key] = $this->term->label();
    $this->drupalPost('node/add/page', $edit, t('Preview'));

    // Check that the preview is displaying the title, body and term.
    $this->assertTitle(t('Preview | Drupal'), 'Basic page title is preview.');
    $this->assertText($edit[$title_key], 'Title displayed.');
    $this->assertText($edit[$body_key], 'Body displayed.');
    $this->assertText($edit[$term_key], 'Term displayed.');

    // Check that the title, body and term fields are displayed with the
    // correct values.
    $this->assertFieldByName($title_key, $edit[$title_key], 'Title field displayed.');
    $this->assertFieldByName($body_key, $edit[$body_key], 'Body field displayed.');
    $this->assertFieldByName($term_key, $edit[$term_key], 'Term field displayed.');

    // Save the node.
    $this->drupalPost('node/add/page', $edit, t('Save'));
    $node = $this->drupalGetNodeByTitle($edit[$title_key]);

    // Check the term was displayed on the saved node.
    $this->drupalGet('node/' . $node->id());
    $this->assertText($edit[$term_key], 'Term displayed.');

    // Check the term appears again on the edit form.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertFieldByName($term_key, $edit[$term_key], 'Term field displayed.');

    // Check with two new terms on the edit form, additionally to the existing
    // one.
    $edit = array();
    $newterm1 = $this->randomName(8);
    $newterm2 = $this->randomName(8);
    $edit[$term_key] = $this->term->label() . ', ' . $newterm1 . ', ' . $newterm2;
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Preview'));
    $this->assertRaw('>' . $newterm1 . '<', 'First new term displayed.');
    $this->assertRaw('>' . $newterm2 . '<', 'Second new term displayed.');
    // The first term should be displayed as link, the others not.
    $this->assertLink($this->term->label());
    $this->assertNoLink($newterm1);
    $this->assertNoLink($newterm2);

    $this->drupalPost('node/add/page', $edit, t('Save'));

    // Check with one more new term, keeping old terms, removing the existing
    // one.
    $edit = array();
    $newterm3 = $this->randomName(8);
    $edit[$term_key] = $newterm1 . ', ' . $newterm3 . ', ' . $newterm2;
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Preview'));
    $this->assertRaw('>' . $newterm1 . '<', 'First existing term displayed.');
    $this->assertRaw('>' . $newterm2 . '<', 'Second existing term displayed.');
    $this->assertRaw('>' . $newterm3 . '<', 'Third new term displayed.');
    $this->assertNoText($this->term->label());
    $this->assertNoLink($newterm1);
    $this->assertNoLink($newterm2);
    $this->assertNoLink($newterm3);
    $this->drupalPost('node/add/page', $edit, t('Save'));
  }

  /**
   * Checks the node preview functionality, when using revisions.
   */
  function testPagePreviewWithRevisions() {
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $title_key = "title";
    $body_key = "body[$langcode][0][value]";
    $term_key = "{$this->field_name}[$langcode]";
    // Force revision on "Basic page" content.
    $this->container->get('config.factory')->get('node.type.page')->set('settings.node.options', array('status', 'revision'))->save();

    // Fill in node creation form and preview node.
    $edit = array();
    $edit[$title_key] = $this->randomName(8);
    $edit[$body_key] = $this->randomName(16);
    $edit[$term_key] = $this->term->id();
    $edit['log'] = $this->randomName(32);
    $this->drupalPost('node/add/page', $edit, t('Preview'));

    // Check that the preview is displaying the title, body and term.
    $this->assertTitle(t('Preview | Drupal'), 'Basic page title is preview.');
    $this->assertText($edit[$title_key], 'Title displayed.');
    $this->assertText($edit[$body_key], 'Body displayed.');
    $this->assertText($edit[$term_key], 'Term displayed.');

    // Check that the title, body and term fields are displayed with the correct values.
    $this->assertFieldByName($title_key, $edit[$title_key], 'Title field displayed.');
    $this->assertFieldByName($body_key, $edit[$body_key], 'Body field displayed.');
    $this->assertFieldByName($term_key, $edit[$term_key], 'Term field displayed.');

    // Check that the log field has the correct value.
    $this->assertFieldByName('log', $edit['log'], 'Log field displayed.');
  }

}
