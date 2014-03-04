<?php

/**
 * @file
 * Definition of Drupal\comment\Tests\CommentPagerTest.
 */

namespace Drupal\comment\Tests;

/**
 * Verifies pagination of comments.
 */
class CommentPagerTest extends CommentTestBase {
  public static function getInfo() {
    return array(
      'name' => 'Comment paging settings',
      'description' => 'Test paging of comments and their settings.',
      'group' => 'Comment',
    );
  }

  /**
   * Confirms comment paging works correctly with flat and threaded comments.
   */
  function testCommentPaging() {
    $this->drupalLogin($this->admin_user);

    // Set comment variables.
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(TRUE);
    $this->setCommentPreview(DRUPAL_DISABLED);

    // Create a node and three comments.
    $node = $this->drupalCreateNode(array('type' => 'article', 'promote' => 1));
    $comments = array();
    $comments[] = $this->postComment($node, $this->randomName(), $this->randomName(), TRUE);
    $comments[] = $this->postComment($node, $this->randomName(), $this->randomName(), TRUE);
    $comments[] = $this->postComment($node, $this->randomName(), $this->randomName(), TRUE);

    $this->setCommentSettings('comment_default_mode', COMMENT_MODE_FLAT, 'Comment paging changed.');

    // Set comments to one per page so that we are able to test paging without
    // needing to insert large numbers of comments.
    $this->setCommentsPerPage(1);

    // Check the first page of the node, and confirm the correct comments are
    // shown.
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw(t('next'), 'Paging links found.');
    $this->assertTrue($this->commentExists($comments[0]), 'Comment 1 appears on page 1.');
    $this->assertFalse($this->commentExists($comments[1]), 'Comment 2 does not appear on page 1.');
    $this->assertFalse($this->commentExists($comments[2]), 'Comment 3 does not appear on page 1.');

    // Check the second page.
    $this->drupalGet('node/' . $node->id(), array('query' => array('page' => 1)));
    $this->assertTrue($this->commentExists($comments[1]), 'Comment 2 appears on page 2.');
    $this->assertFalse($this->commentExists($comments[0]), 'Comment 1 does not appear on page 2.');
    $this->assertFalse($this->commentExists($comments[2]), 'Comment 3 does not appear on page 2.');

    // Check the third page.
    $this->drupalGet('node/' . $node->id(), array('query' => array('page' => 2)));
    $this->assertTrue($this->commentExists($comments[2]), 'Comment 3 appears on page 3.');
    $this->assertFalse($this->commentExists($comments[0]), 'Comment 1 does not appear on page 3.');
    $this->assertFalse($this->commentExists($comments[1]), 'Comment 2 does not appear on page 3.');

    // Post a reply to the oldest comment and test again.
    $replies = array();
    $oldest_comment = reset($comments);
    $this->drupalGet('comment/reply/' . $node->id() . '/' . $oldest_comment->id());
    $reply = $this->postComment(NULL, $this->randomName(), $this->randomName(), TRUE);

    $this->setCommentsPerPage(2);
    // We are still in flat view - the replies should not be on the first page,
    // even though they are replies to the oldest comment.
    $this->drupalGet('node/' . $node->id(), array('query' => array('page' => 0)));
    $this->assertFalse($this->commentExists($reply, TRUE), 'In flat mode, reply does not appear on page 1.');

    // If we switch to threaded mode, the replies on the oldest comment
    // should be bumped to the first page and comment 6 should be bumped
    // to the second page.
    $this->setCommentSettings('comment_default_mode', COMMENT_MODE_THREADED, 'Switched to threaded mode.');
    $this->drupalGet('node/' . $node->id(), array('query' => array('page' => 0)));
    $this->assertTrue($this->commentExists($reply, TRUE), 'In threaded mode, reply appears on page 1.');
    $this->assertFalse($this->commentExists($comments[1]), 'In threaded mode, comment 2 has been bumped off of page 1.');

    // If (# replies > # comments per page) in threaded expanded view,
    // the overage should be bumped.
    $reply2 = $this->postComment(NULL, $this->randomName(), $this->randomName(), TRUE);
    $this->drupalGet('node/' . $node->id(), array('query' => array('page' => 0)));
    $this->assertFalse($this->commentExists($reply2, TRUE), 'In threaded mode where # replies > # comments per page, the newest reply does not appear on page 1.');

    $this->drupalLogout();
  }

  /**
   * Tests comment ordering and threading.
   */
  function testCommentOrderingThreading() {
    $this->drupalLogin($this->admin_user);

    // Set comment variables.
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(TRUE);
    $this->setCommentPreview(DRUPAL_DISABLED);

    // Display all the comments on the same page.
    $this->setCommentsPerPage(1000);

    // Create a node and three comments.
    $node = $this->drupalCreateNode(array('type' => 'article', 'promote' => 1));
    $comments = array();
    $comments[] = $this->postComment($node, $this->randomName(), $this->randomName(), TRUE);
    $comments[] = $this->postComment($node, $this->randomName(), $this->randomName(), TRUE);
    $comments[] = $this->postComment($node, $this->randomName(), $this->randomName(), TRUE);

    // Post a reply to the second comment.
    $this->drupalGet('comment/reply/' . $node->id() . '/' . $comments[1]->id());
    $comments[] = $this->postComment(NULL, $this->randomName(), $this->randomName(), TRUE);

    // Post a reply to the first comment.
    $this->drupalGet('comment/reply/' . $node->id() . '/' . $comments[0]->id());
    $comments[] = $this->postComment(NULL, $this->randomName(), $this->randomName(), TRUE);

    // Post a reply to the last comment.
    $this->drupalGet('comment/reply/' . $node->id() . '/' . $comments[2]->id());
    $comments[] = $this->postComment(NULL, $this->randomName(), $this->randomName(), TRUE);

    // Post a reply to the second comment.
    $this->drupalGet('comment/reply/' . $node->id() . '/' . $comments[3]->id());
    $comments[] = $this->postComment(NULL, $this->randomName(), $this->randomName(), TRUE);

    // At this point, the comment tree is:
    // - 0
    //   - 4
    // - 1
    //   - 3
    //     - 6
    // - 2
    //   - 5

    $this->setCommentSettings('comment_default_mode', COMMENT_MODE_FLAT, 'Comment paging changed.');

    $expected_order = array(
      0,
      1,
      2,
      3,
      4,
      5,
      6,
    );
    $this->drupalGet('node/' . $node->id());
    $this->assertCommentOrder($comments, $expected_order);

    $this->setCommentSettings('comment_default_mode', COMMENT_MODE_THREADED, 'Switched to threaded mode.');

    $expected_order = array(
      0,
      4,
      1,
      3,
      6,
      2,
      5,
    );
    $this->drupalGet('node/' . $node->id());
    $this->assertCommentOrder($comments, $expected_order);
  }

  /**
   * Asserts that the comments are displayed in the correct order.
   *
   * @param $comments
   *   And array of comments.
   * @param $expected_order
   *   An array of keys from $comments describing the expected order.
   */
  function assertCommentOrder(array $comments, array $expected_order) {
    $expected_cids = array();

    // First, rekey the expected order by cid.
    foreach ($expected_order as $key) {
      $expected_cids[] = $comments[$key]->id();
    }

    $comment_anchors = $this->xpath('//a[starts-with(@id,"comment-")]');
    $result_order = array();
    foreach ($comment_anchors as $anchor) {
      $result_order[] = substr($anchor['id'], 8);
    }
    return $this->assertEqual($expected_cids, $result_order, format_string('Comment order: expected @expected, returned @returned.', array('@expected' => implode(',', $expected_cids), '@returned' => implode(',', $result_order))));
  }

  /**
   * Tests comment_new_page_count().
   */
  function testCommentNewPageIndicator() {
    $this->drupalLogin($this->admin_user);

    // Set comment variables.
    $this->setCommentForm(TRUE);
    $this->setCommentSubject(TRUE);
    $this->setCommentPreview(DRUPAL_DISABLED);

    // Set comments to one per page so that we are able to test paging without
    // needing to insert large numbers of comments.
    $this->setCommentsPerPage(1);

    // Create a node and three comments.
    $node = $this->drupalCreateNode(array('type' => 'article', 'promote' => 1));
    $comments = array();
    $comments[] = $this->postComment($node, $this->randomName(), $this->randomName(), TRUE);
    $comments[] = $this->postComment($node, $this->randomName(), $this->randomName(), TRUE);
    $comments[] = $this->postComment($node, $this->randomName(), $this->randomName(), TRUE);

    // Post a reply to the second comment.
    $this->drupalGet('comment/reply/' . $node->id() . '/' . $comments[1]->id());
    $comments[] = $this->postComment(NULL, $this->randomName(), $this->randomName(), TRUE);

    // Post a reply to the first comment.
    $this->drupalGet('comment/reply/' . $node->id() . '/' . $comments[0]->id());
    $comments[] = $this->postComment(NULL, $this->randomName(), $this->randomName(), TRUE);

    // Post a reply to the last comment.
    $this->drupalGet('comment/reply/' . $node->id() . '/' . $comments[2]->id());
    $comments[] = $this->postComment(NULL, $this->randomName(), $this->randomName(), TRUE);

    // At this point, the comment tree is:
    // - 0
    //   - 4
    // - 1
    //   - 3
    // - 2
    //   - 5

    $this->setCommentSettings('comment_default_mode', COMMENT_MODE_FLAT, 'Comment paging changed.');

    $expected_pages = array(
      1 => 5, // Page of comment 5
      2 => 4, // Page of comment 4
      3 => 3, // Page of comment 3
      4 => 2, // Page of comment 2
      5 => 1, // Page of comment 1
      6 => 0, // Page of comment 0
    );

    $node = node_load($node->id());
    foreach ($expected_pages as $new_replies => $expected_page) {
      $returned = comment_new_page_count($node->comment_count, $new_replies, $node);
      $returned_page = is_array($returned) ? $returned['page'] : 0;
      $this->assertIdentical($expected_page, $returned_page, format_string('Flat mode, @new replies: expected page @expected, returned page @returned.', array('@new' => $new_replies, '@expected' => $expected_page, '@returned' => $returned_page)));
    }

    $this->setCommentSettings('comment_default_mode', COMMENT_MODE_THREADED, 'Switched to threaded mode.');

    $expected_pages = array(
      1 => 5, // Page of comment 5
      2 => 1, // Page of comment 4
      3 => 1, // Page of comment 4
      4 => 1, // Page of comment 4
      5 => 1, // Page of comment 4
      6 => 0, // Page of comment 0
    );

    $node = node_load($node->id());
    foreach ($expected_pages as $new_replies => $expected_page) {
      $returned = comment_new_page_count($node->comment_count, $new_replies, $node);
      $returned_page = is_array($returned) ? $returned['page'] : 0;
      $this->assertEqual($expected_page, $returned_page, format_string('Threaded mode, @new replies: expected page @expected, returned page @returned.', array('@new' => $new_replies, '@expected' => $expected_page, '@returned' => $returned_page)));
    }
  }
}
