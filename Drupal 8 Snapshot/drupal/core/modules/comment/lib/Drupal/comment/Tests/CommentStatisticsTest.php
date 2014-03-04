<?php

/**
 * @file
 * Contains Drupal\comment\Tests\CommentStatisticsTest.
 */

namespace Drupal\comment\Tests;

/**
 * Tests the comment module administrative and end-user-facing interfaces.
 */
class CommentStatisticsTest extends CommentTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Comment statistics',
      'description' => 'Test comment statistics on nodes.',
      'group' => 'Comment',
    );
  }

  function setUp() {
    parent::setUp();

    // Create a second user to post comments.
    $this->web_user2 = $this->drupalCreateUser(array(
      'post comments',
      'create article content',
      'edit own comments',
      'post comments',
      'skip comment approval',
      'access comments',
      'access content',
    ));
  }

  /**
   * Tests the node comment statistics.
   */
  function testCommentNodeCommentStatistics() {
    // Set comments to have subject and preview disabled.
    $this->drupalLogin($this->admin_user);
    $this->setCommentPreview(DRUPAL_DISABLED);
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(FALSE);
    $this->setCommentSettings('comment_default_mode', COMMENT_MODE_THREADED, 'Comment paging changed.');
    $this->drupalLogout();

    // Checks the initial values of node comment statistics with no comment.
    $node = node_load($this->node->id());
    $this->assertEqual($node->last_comment_timestamp, $this->node->created, 'The initial value of node last_comment_timestamp is the node created date.');
    $this->assertEqual($node->last_comment_name, NULL, 'The initial value of node last_comment_name is NULL.');
    $this->assertEqual($node->last_comment_uid, $this->web_user->id(), 'The initial value of node last_comment_uid is the node uid.');
    $this->assertEqual($node->comment_count, 0, 'The initial value of node comment_count is zero.');

    // Post comment #1 as web_user2.
    $this->drupalLogin($this->web_user2);
    $comment_text = $this->randomName();
    $this->postComment($this->node, $comment_text);

    // Checks the new values of node comment statistics with comment #1.
    // The node needs to be reloaded with a node_load_multiple cache reset.
    $node = node_load($this->node->id(), TRUE);
    $this->assertEqual($node->last_comment_name, NULL, 'The value of node last_comment_name is NULL.');
    $this->assertEqual($node->last_comment_uid, $this->web_user2->id(), 'The value of node last_comment_uid is the comment #1 uid.');
    $this->assertEqual($node->comment_count, 1, 'The value of node comment_count is 1.');

    // Prepare for anonymous comment submission (comment approval enabled).
    $this->drupalLogin($this->admin_user);
    user_role_change_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access comments' => TRUE,
      'post comments' => TRUE,
      'skip comment approval' => FALSE,
    ));
    // Ensure that the poster can leave some contact info.
    $this->setCommentAnonymous('1');
    $this->drupalLogout();

    // Post comment #2 as anonymous (comment approval enabled).
    $this->drupalGet('comment/reply/' . $this->node->id());
    $this->postComment($this->node, $this->randomName(), '', TRUE);

    // Checks the new values of node comment statistics with comment #2 and
    // ensure they haven't changed since the comment has not been moderated.
    // The node needs to be reloaded with a node_load_multiple cache reset.
    $node = node_load($this->node->id(), TRUE);
    $this->assertEqual($node->last_comment_name, NULL, 'The value of node last_comment_name is still NULL.');
    $this->assertEqual($node->last_comment_uid, $this->web_user2->id(), 'The value of node last_comment_uid is still the comment #1 uid.');
    $this->assertEqual($node->comment_count, 1, 'The value of node comment_count is still 1.');

    // Prepare for anonymous comment submission (no approval required).
    $this->drupalLogin($this->admin_user);
    user_role_change_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access comments' => TRUE,
      'post comments' => TRUE,
      'skip comment approval' => TRUE,
    ));
    $this->drupalLogout();

    // Post comment #3 as anonymous.
    $this->drupalGet('comment/reply/' . $this->node->id());
    $comment_loaded = $this->postComment($this->node, $this->randomName(), '', array('name' => $this->randomName()));

    // Checks the new values of node comment statistics with comment #3.
    // The node needs to be reloaded with a node_load_multiple cache reset.
    $node = node_load($this->node->id(), TRUE);
    $this->assertEqual($node->last_comment_name, $comment_loaded->name->value, 'The value of node last_comment_name is the name of the anonymous user.');
    $this->assertEqual($node->last_comment_uid, 0, 'The value of node last_comment_uid is zero.');
    $this->assertEqual($node->comment_count, 2, 'The value of node comment_count is 2.');
  }

}
