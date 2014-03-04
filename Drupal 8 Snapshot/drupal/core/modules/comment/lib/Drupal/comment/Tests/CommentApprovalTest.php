<?php

/**
 * @file
 * Definition of Drupal\comment\Tests\CommentApprovalTest.
 */

namespace Drupal\comment\Tests;

/**
 * Tests comment approval functionality.
 */
class CommentApprovalTest extends CommentTestBase {
  public static function getInfo() {
    return array(
      'name' => 'Comment approval',
      'description' => 'Test comment approval functionality.',
      'group' => 'Comment',
    );
  }

  /**
   * Test comment approval functionality through admin/content/comment.
   */
  function testApprovalAdminInterface() {
    // Set anonymous comments to require approval.
    user_role_change_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access comments' => TRUE,
      'post comments' => TRUE,
      'skip comment approval' => FALSE,
    ));
    $this->drupalLogin($this->admin_user);
    $this->setCommentAnonymous('0'); // Ensure that doesn't require contact info.

    // Test that the comments page loads correctly when there are no comments
    $this->drupalGet('admin/content/comment');
    $this->assertText(t('No comments available.'));

    $this->drupalLogout();

    // Post anonymous comment without contact info.
    $subject = $this->randomName();
    $body = $this->randomName();
    $this->postComment($this->node, $body, $subject, TRUE); // Set $contact to true so that it won't check for id and message.
    $this->assertText(t('Your comment has been queued for review by site administrators and will be published after approval.'), 'Comment requires approval.');

    // Get unapproved comment id.
    $this->drupalLogin($this->admin_user);
    $anonymous_comment4 = $this->getUnapprovedComment($subject);
    $anonymous_comment4 = entity_create('comment', array('cid' => $anonymous_comment4, 'node_type' => '', 'subject' => $subject, 'comment_body' => $body, 'nid' => $this->node->id()));
    $this->drupalLogout();

    $this->assertFalse($this->commentExists($anonymous_comment4), 'Anonymous comment was not published.');

    // Approve comment.
    $this->drupalLogin($this->admin_user);
    $this->performCommentOperation($anonymous_comment4, 'publish', TRUE);
    $this->drupalLogout();

    $this->drupalGet('node/' . $this->node->id());
    $this->assertTrue($this->commentExists($anonymous_comment4), 'Anonymous comment visible.');

    // Post 2 anonymous comments without contact info.
    $comments[] = $this->postComment($this->node, $this->randomName(), $this->randomName(), TRUE);
    $comments[] = $this->postComment($this->node, $this->randomName(), $this->randomName(), TRUE);

    // Publish multiple comments in one operation.
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/content/comment/approval');
    $this->assertText(t('Unapproved comments (@count)', array('@count' => 2)), 'Two unapproved comments waiting for approval.');
    $edit = array(
      "comments[{$comments[0]->id()}]" => 1,
      "comments[{$comments[1]->id()}]" => 1,
    );
    $this->drupalPost(NULL, $edit, t('Update'));
    $this->assertText(t('Unapproved comments (@count)', array('@count' => 0)), 'All comments were approved.');

    // Delete multiple comments in one operation.
    $edit = array(
      'operation' => 'delete',
      "comments[{$comments[0]->id()}]" => 1,
      "comments[{$comments[1]->id()}]" => 1,
      "comments[{$anonymous_comment4->id()}]" => 1,
    );
    $this->drupalPost(NULL, $edit, t('Update'));
    $this->assertText(t('Are you sure you want to delete these comments and all their children?'), 'Confirmation required.');
    $this->drupalPost(NULL, $edit, t('Delete comments'));
    $this->assertText(t('No comments available.'), 'All comments were deleted.');
  }

  /**
   * Tests comment approval functionality through the node interface.
   */
  function testApprovalNodeInterface() {
    // Set anonymous comments to require approval.
    user_role_change_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access comments' => TRUE,
      'post comments' => TRUE,
      'skip comment approval' => FALSE,
    ));
    $this->drupalLogin($this->admin_user);
    $this->setCommentAnonymous('0'); // Ensure that doesn't require contact info.
    $this->drupalLogout();

    // Post anonymous comment without contact info.
    $subject = $this->randomName();
    $body = $this->randomName();
    $this->postComment($this->node, $body, $subject, TRUE); // Set $contact to true so that it won't check for id and message.
    $this->assertText(t('Your comment has been queued for review by site administrators and will be published after approval.'), 'Comment requires approval.');

    // Get unapproved comment id.
    $this->drupalLogin($this->admin_user);
    $anonymous_comment4 = $this->getUnapprovedComment($subject);
    $anonymous_comment4 = entity_create('comment', array('cid' => $anonymous_comment4, 'node_type' => '', 'subject' => $subject, 'comment_body' => $body, 'nid' => $this->node->id()));
    $this->drupalLogout();

    $this->assertFalse($this->commentExists($anonymous_comment4), 'Anonymous comment was not published.');

    // Approve comment.
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('comment/1/approve');
    $this->assertResponse(403, 'Forged comment approval was denied.');
    $this->drupalGet('comment/1/approve', array('query' => array('token' => 'forged')));
    $this->assertResponse(403, 'Forged comment approval was denied.');
    $this->drupalGet('node/' . $this->node->id());
    $this->clickLink(t('approve'));
    $this->drupalLogout();

    $this->drupalGet('node/' . $this->node->id());
    $this->assertTrue($this->commentExists($anonymous_comment4), 'Anonymous comment visible.');
  }
}
