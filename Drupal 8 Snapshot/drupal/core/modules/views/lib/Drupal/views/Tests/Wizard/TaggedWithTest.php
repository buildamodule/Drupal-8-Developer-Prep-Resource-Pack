<?php

/**
 * @file
 * Definition of Drupal\views\Tests\Wizard\TaggedWithTest.
 */

namespace Drupal\views\Tests\Wizard;

use Drupal\Core\Language\Language;

/**
 * Tests the ability of the views wizard to create views filtered by taxonomy.
 */
class TaggedWithTest extends WizardTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('taxonomy');

  protected $node_type_with_tags;

  protected $node_type_without_tags;

  protected $tag_vocabulary;

  protected $tag_field;

  protected $tag_instance;

  public static function getInfo() {
    return array(
      'name' => 'Taxonomy functionality',
      'description' => 'Test the ability of the views wizard to create views filtered by taxonomy.',
      'group' => 'Views Wizard',
    );
  }

  function setUp() {
    parent::setUp();

    // Create two content types. One will have an autocomplete tagging field,
    // and one won't.
    $this->node_type_with_tags = $this->drupalCreateContentType();
    $this->node_type_without_tags = $this->drupalCreateContentType();

    // Create the vocabulary for the tag field.
    $this->tag_vocabulary = entity_create('taxonomy_vocabulary',  array(
      'name' => 'Views testing tags',
      'vid' => 'views_testing_tags',
    ));
    $this->tag_vocabulary->save();

    // Create the tag field itself.
    $this->tag_field = entity_create('field_entity', array(
      'field_name' => 'field_views_testing_tags',
      'type' => 'taxonomy_term_reference',
      'cardinality' => FIELD_CARDINALITY_UNLIMITED,
      'settings' => array(
        'allowed_values' => array(
          array(
            'vocabulary' => $this->tag_vocabulary->id(),
            'parent' => 0,
          ),
        ),
      ),
    ));
    $this->tag_field->save();

    // Create an instance of the tag field on one of the content types, and
    // configure it to display an autocomplete widget.
    $this->tag_instance = array(
      'field_name' => 'field_views_testing_tags',
      'entity_type' => 'node',
      'bundle' => $this->node_type_with_tags->type,
    );
    entity_create('field_instance', $this->tag_instance)->save();

    entity_get_form_display('node', $this->node_type_with_tags->type, 'default')
      ->setComponent('field_views_testing_tags', array(
        'type' => 'taxonomy_autocomplete',
      ))
      ->save();

    entity_get_display('node', $this->node_type_with_tags->type, 'default')
      ->setComponent('field_views_testing_tags', array(
        'type' => 'taxonomy_term_reference_link',
        'weight' => 10,
      ))
      ->save();
    entity_get_display('node', $this->node_type_with_tags->type, 'teaser')
      ->setComponent('field_views_testing_tags', array(
        'type' => 'taxonomy_term_reference_link',
        'weight' => 10,
      ))
      ->save();
  }

  /**
   * Tests the "tagged with" functionality.
   */
  function testTaggedWith() {
    // In this test we will only create nodes that have an instance of the tag
    // field.
    $node_add_path = 'node/add/' . $this->node_type_with_tags->type;

    // Create three nodes, with different tags.
    $tag_field = $this->tag_field->id() . '[' . Language::LANGCODE_NOT_SPECIFIED . ']';
    $edit = array();
    $edit['title'] = $node_tag1_title = $this->randomName();
    $edit[$tag_field] = 'tag1';
    $this->drupalPost($node_add_path, $edit, t('Save'));
    $edit = array();
    $edit['title'] = $node_tag1_tag2_title = $this->randomName();
    $edit[$tag_field] = 'tag1, tag2';
    $this->drupalPost($node_add_path, $edit, t('Save'));
    $edit = array();
    $edit['title'] = $node_no_tags_title = $this->randomName();
    $this->drupalPost($node_add_path, $edit, t('Save'));

    // Create a view that filters by taxonomy term "tag1". It should show only
    // the two nodes from above that are tagged with "tag1".
    $view1 = array();
    // First select the node type and update the form so the correct tag field
    // is used.
    $view1['show[type]'] = $this->node_type_with_tags->type;
    $this->drupalPost('admin/structure/views/add', $view1, t('Update "of type" choice'));
    // Now resubmit the entire form to the same URL.
    $view1['label'] = $this->randomName(16);
    $view1['id'] = strtolower($this->randomName(16));
    $view1['description'] = $this->randomName(16);
    $view1['show[tagged_with]'] = 'tag1';
    $view1['page[create]'] = 1;
    $view1['page[title]'] = $this->randomName(16);
    $view1['page[path]'] = $this->randomName(16);
    $this->drupalPost(NULL, $view1, t('Save and edit'));
    // Visit the page and check that the nodes we expect are present and the
    // ones we don't expect are absent.
    $this->drupalGet($view1['page[path]']);
    $this->assertResponse(200);
    $this->assertText($node_tag1_title);
    $this->assertText($node_tag1_tag2_title);
    $this->assertNoText($node_no_tags_title);

    // Create a view that filters by taxonomy term "tag2". It should show only
    // the one node from above that is tagged with "tag2".
    $view2 = array();
    $view2['show[type]'] = $this->node_type_with_tags->type;
    $this->drupalPost('admin/structure/views/add', $view2, t('Update "of type" choice'));
    $this->assertResponse(200);
    $view2['label'] = $this->randomName(16);
    $view2['id'] = strtolower($this->randomName(16));
    $view2['description'] = $this->randomName(16);
    $view2['show[tagged_with]'] = 'tag2';
    $view2['page[create]'] = 1;
    $view2['page[title]'] = $this->randomName(16);
    $view2['page[path]'] = $this->randomName(16);
    $this->drupalPost(NULL, $view2, t('Save and edit'));
    $this->assertResponse(200);
    $this->drupalGet($view2['page[path]']);
    $this->assertNoText($node_tag1_title);
    $this->assertText($node_tag1_tag2_title);
    $this->assertNoText($node_no_tags_title);
  }

  /**
   * Tests that the "tagged with" form element only shows for node types that support it.
   */
  function testTaggedWithByNodeType() {
    // The tagging field is associated with one of our node types only. So the
    // "tagged with" form element on the view wizard should appear on the form
    // by default (when the wizard is configured to display all content) and
    // also when the node type that has the tagging field is selected, but not
    // when the node type that doesn't have the tagging field is selected.
    $tags_xpath = '//input[@name="show[tagged_with]"]';
    $this->drupalGet('admin/structure/views/add');
    $this->assertFieldByXpath($tags_xpath);
    $view['show[type]'] = $this->node_type_with_tags->type;
    $this->drupalPost('admin/structure/views/add', $view, t('Update "of type" choice'));
    $this->assertFieldByXpath($tags_xpath);
    $view['show[type]'] = $this->node_type_without_tags->type;
    $this->drupalPost(NULL, $view, t('Update "of type" choice'));
    $this->assertNoFieldByXpath($tags_xpath);

    // If we add an instance of the tagging field to the second node type, the
    // "tagged with" form element should not appear for it too.
    $instance = $this->tag_instance;
    $instance['bundle'] = $this->node_type_without_tags->type;
    entity_create('field_instance', $instance)->save();
    entity_get_form_display('node', $this->node_type_without_tags->type, 'default')
      ->setComponent('field_views_testing_tags', array(
        'type' => 'taxonomy_autocomplete',
      ))
      ->save();

    $view['show[type]'] = $this->node_type_with_tags->type;
    $this->drupalPost('admin/structure/views/add', $view, t('Update "of type" choice'));
    $this->assertFieldByXpath($tags_xpath);
    $view['show[type]'] = $this->node_type_without_tags->type;
    $this->drupalPost(NULL, $view, t('Update "of type" choice'));
    $this->assertFieldByXpath($tags_xpath);
  }

}
