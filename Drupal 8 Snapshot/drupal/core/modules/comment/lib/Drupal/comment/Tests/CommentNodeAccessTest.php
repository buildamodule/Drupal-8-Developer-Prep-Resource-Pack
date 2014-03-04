<?php

/**
 * @file
 * Definition of Drupal\comment\Tests\CommentNodeAccessTest.
 */

namespace Drupal\comment\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests comments with node access.
 *
 * Verifies there is no PostgreSQL error when viewing a node with threaded
 * comments (a comment and a reply), if a node access module is in use.
 */
class CommentNodeAccessTest extends CommentTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node_access_test');

  public static function getInfo() {
    return array(
      'name' => 'Comment node access',
      'description' => 'Test comment viewing with node access.',
      'group' => 'Comment',
    );
  }

  function setUp() {
    parent::setUp();

    node_access_rebuild();

    // Re-create user.
    $this->web_user = $this->drupalCreateUser(array(
      'access comments',
      'post comments',
      'create article content',
      'edit own comments',
      'node test view',
      'skip comment approval',
    ));
  }

  /**
   * Test that threaded comments can be viewed.
   */
  function testThreadedCommentView() {
    // Set comments to have subject required and preview disabled.
    $this->drupalLogin($this->admin_user);
    $this->setCommentPreview(DRUPAL_DISABLED);
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(TRUE);
    $this->setCommentSettings('comment_default_mode', COMMENT_MODE_THREADED, 'Comment paging changed.');
    $this->drupalLogout();

    // Post comment.
    $this->drupalLogin($this->web_user);
    $comment_text = $this->randomName();
    $comment_subject = $this->randomName();
    $comment = $this->postComment($this->node, $comment_text, $comment_subject);
    $this->assertTrue($this->commentExists($comment), 'Comment found.');

    // Check comment display.
    $this->drupalGet('node/' . $this->node->id() . '/' . $comment->id());
    $this->assertText($comment_subject, 'Individual comment subject found.');
    $this->assertText($comment_text, 'Individual comment body found.');

    // Reply to comment, creating second comment.
    $this->drupalGet('comment/reply/' . $this->node->id() . '/' . $comment->id());
    $reply_text = $this->randomName();
    $reply_subject = $this->randomName();
    $reply = $this->postComment(NULL, $reply_text, $reply_subject, TRUE);
    $this->assertTrue($this->commentExists($reply, TRUE), 'Reply found.');

    // Go to the node page and verify comment and reply are visible.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertText($comment_text);
    $this->assertText($comment_subject);
    $this->assertText($reply_text);
    $this->assertText($reply_subject);
  }
}
