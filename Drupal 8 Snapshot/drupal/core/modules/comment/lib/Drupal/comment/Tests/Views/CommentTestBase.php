<?php

/**
 * @file
 * Contains \Drupal\comment\Tests\Views\CommentTestBase.
 */

namespace Drupal\comment\Tests\Views;

use Drupal\views\Tests\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the argument_comment_user_uid handler.
 */
abstract class CommentTestBase extends ViewTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('comment', 'comment_test_views');

  /**
   * Stores a comment used by the tests.
   *
   * @var \Drupal\comment\Plugin\Core\Entity\Comment
   */
  public $comment;

  function setUp() {
    parent::setUp();

    ViewTestData::importTestViews(get_class($this), array('comment_test_views'));

    // Add two users, create a node with the user1 as author and another node
    // with user2 as author. For the second node add a comment from user1.
    $this->account = $this->drupalCreateUser();
    $this->account2 = $this->drupalCreateUser();
    $this->drupalLogin($this->account);

    $this->node_user_posted = $this->drupalCreateNode();
    $this->node_user_commented = $this->drupalCreateNode(array('uid' => $this->account2->uid));

    $comment = array(
      'uid' => $this->loggedInUser->id(),
      'nid' => $this->node_user_commented->id(),
      'cid' => '',
      'pid' => '',
      'node_type' => '',
    );
    $this->comment = entity_create('comment', $comment);
    $this->comment->save();
  }

}
