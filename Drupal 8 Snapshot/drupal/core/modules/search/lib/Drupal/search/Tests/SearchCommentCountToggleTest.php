<?php

/**
 * @file
 * Definition of Drupal\search\Tests\SearchCommentCountToggleTest.
 */

namespace Drupal\search\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests that comment count display toggles properly on comment status of node
 *
 * Issue 537278
 *
 * - Nodes with comment status set to Open should always how comment counts
 * - Nodes with comment status set to Closed should show comment counts
 *     only when there are comments
 * - Nodes with comment status set to Hidden should never show comment counts
 */
class SearchCommentCountToggleTest extends SearchTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('comment');

  // Requires node types, comment config, filter formats.
  protected $profile = 'standard';

  protected $searching_user;
  protected $searchable_nodes;

  public static function getInfo() {
    return array(
      'name' => 'Comment count toggle',
      'description' => 'Verify that comment count display toggles properly on comment status of node.',
      'group' => 'Search',
    );
  }

  function setUp() {
    parent::setUp();

    // Create searching user.
    $this->searching_user = $this->drupalCreateUser(array('search content', 'access content', 'access comments', 'skip comment approval'));

    // Create initial nodes.
    $node_params = array('type' => 'article', 'body' => array(array('value' => 'SearchCommentToggleTestCase')));

    $this->searchable_nodes['1 comment'] = $this->drupalCreateNode($node_params);
    $this->searchable_nodes['0 comments'] = $this->drupalCreateNode($node_params);

    // Login with sufficient privileges.
    $this->drupalLogin($this->searching_user);

    // Create a comment array
    $edit_comment = array();
    $edit_comment['subject'] = $this->randomName();
    $edit_comment['comment_body[' . Language::LANGCODE_NOT_SPECIFIED . '][0][value]'] = $this->randomName();

    // Post comment to the test node with comment
    $this->drupalPost('comment/reply/' . $this->searchable_nodes['1 comment']->id(), $edit_comment, t('Save'));

    // First update the index. This does the initial processing.
    node_update_index();

    // Then, run the shutdown function. Testing is a unique case where indexing
    // and searching has to happen in the same request, so running the shutdown
    // function manually is needed to finish the indexing process.
    search_update_totals();
  }

  /**
   * Verify that comment count display toggles properly on comment status of node
   */
  function testSearchCommentCountToggle() {
    // Search for the nodes by string in the node body.
    $edit = array(
      'search_block_form' => "'SearchCommentToggleTestCase'",
    );

    // Test comment count display for nodes with comment status set to Open
    $this->drupalPost('', $edit, t('Search'));
    $this->assertText(t('0 comments'), 'Empty comment count displays for nodes with comment status set to Open');
    $this->assertText(t('1 comment'), 'Non-empty comment count displays for nodes with comment status set to Open');

    // Test comment count display for nodes with comment status set to Closed
    $this->searchable_nodes['0 comments']->comment = COMMENT_NODE_CLOSED;
    $this->searchable_nodes['0 comments']->save();
    $this->searchable_nodes['1 comment']->comment = COMMENT_NODE_CLOSED;
    $this->searchable_nodes['1 comment']->save();

    $this->drupalPost('', $edit, t('Search'));
    $this->assertNoText(t('0 comments'), 'Empty comment count does not display for nodes with comment status set to Closed');
    $this->assertText(t('1 comment'), 'Non-empty comment count displays for nodes with comment status set to Closed');

    // Test comment count display for nodes with comment status set to Hidden
    $this->searchable_nodes['0 comments']->comment = COMMENT_NODE_HIDDEN;
    $this->searchable_nodes['0 comments']->save();
    $this->searchable_nodes['1 comment']->comment = COMMENT_NODE_HIDDEN;
    $this->searchable_nodes['1 comment']->save();

    $this->drupalPost('', $edit, t('Search'));
    $this->assertNoText(t('0 comments'), 'Empty comment count does not display for nodes with comment status set to Hidden');
    $this->assertNoText(t('1 comment'), 'Non-empty comment count does not display for nodes with comment status set to Hidden');
  }
}
