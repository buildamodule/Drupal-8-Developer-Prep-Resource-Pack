<?php

/**
 * @file
 * Contains Drupal\comment\Tests\CommentAnonymousTest.
 */

namespace Drupal\comment\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests anonymous commenting.
 */
class CommentAnonymousTest extends CommentTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Anonymous comments',
      'description' => 'Test anonymous comments.',
      'group' => 'Comment',
    );
  }

  function setUp() {
    parent::setUp();

    // Enable anonymous and authenticated user comments.
    user_role_grant_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access comments',
      'post comments',
      'skip comment approval',
    ));
    user_role_grant_permissions(DRUPAL_AUTHENTICATED_RID, array(
      'access comments',
      'post comments',
      'skip comment approval',
    ));
  }

  /**
   * Tests anonymous comment functionality.
   */
  function testAnonymous() {
    $this->drupalLogin($this->admin_user);
    $this->setCommentAnonymous('0'); // Ensure that doesn't require contact info.
    $this->drupalLogout();

    // Post anonymous comment without contact info.
    $anonymous_comment1 = $this->postComment($this->node, $this->randomName(), $this->randomName());
    $this->assertTrue($this->commentExists($anonymous_comment1), 'Anonymous comment without contact info found.');

    // Allow contact info.
    $this->drupalLogin($this->admin_user);
    $this->setCommentAnonymous('1');

    // Attempt to edit anonymous comment.
    $this->drupalGet('comment/' . $anonymous_comment1->id() . '/edit');
    $edited_comment = $this->postComment(NULL, $this->randomName(), $this->randomName());
    $this->assertTrue($this->commentExists($edited_comment, FALSE), 'Modified reply found.');
    $this->drupalLogout();

    // Post anonymous comment with contact info (optional).
    $this->drupalGet('comment/reply/' . $this->node->id());
    $this->assertTrue($this->commentContactInfoAvailable(), 'Contact information available.');

    $anonymous_comment2 = $this->postComment($this->node, $this->randomName(), $this->randomName());
    $this->assertTrue($this->commentExists($anonymous_comment2), 'Anonymous comment with contact info (optional) found.');

    // Ensure anonymous users cannot post in the name of registered users.
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $edit = array(
      'name' => $this->admin_user->getUsername(),
      'mail' => $this->randomName() . '@example.com',
      'subject' => $this->randomName(),
      "comment_body[$langcode][0][value]" => $this->randomName(),
    );
    $this->drupalPost('comment/reply/' . $this->node->id(), $edit, t('Save'));
    $this->assertText(t('The name you used belongs to a registered user.'));

    // Require contact info.
    $this->drupalLogin($this->admin_user);
    $this->setCommentAnonymous('2');
    $this->drupalLogout();

    // Try to post comment with contact info (required).
    $this->drupalGet('comment/reply/' . $this->node->id());
    $this->assertTrue($this->commentContactInfoAvailable(), 'Contact information available.');

    $anonymous_comment3 = $this->postComment($this->node, $this->randomName(), $this->randomName(), TRUE);
    // Name should have 'Anonymous' for value by default.
    $this->assertText(t('E-mail field is required.'), 'E-mail required.');
    $this->assertFalse($this->commentExists($anonymous_comment3), 'Anonymous comment with contact info (required) not found.');

    // Post comment with contact info (required).
    $author_name = $this->randomName();
    $author_mail = $this->randomName() . '@example.com';
    $anonymous_comment3 = $this->postComment($this->node, $this->randomName(), $this->randomName(), array('name' => $author_name, 'mail' => $author_mail));
    $this->assertTrue($this->commentExists($anonymous_comment3), 'Anonymous comment with contact info (required) found.');

    // Make sure the user data appears correctly when editing the comment.
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('comment/' . $anonymous_comment3->id() . '/edit');
    $this->assertRaw($author_name, "The anonymous user's name is correct when editing the comment.");
    $this->assertRaw($author_mail, "The anonymous user's e-mail address is correct when editing the comment.");

    // Unpublish comment.
    $this->performCommentOperation($anonymous_comment3, 'unpublish');

    $this->drupalGet('admin/content/comment/approval');
    $this->assertRaw('comments[' . $anonymous_comment3->id() . ']', 'Comment was unpublished.');

    // Publish comment.
    $this->performCommentOperation($anonymous_comment3, 'publish', TRUE);

    $this->drupalGet('admin/content/comment');
    $this->assertRaw('comments[' . $anonymous_comment3->id() . ']', 'Comment was published.');

    // Delete comment.
    $this->performCommentOperation($anonymous_comment3, 'delete');

    $this->drupalGet('admin/content/comment');
    $this->assertNoRaw('comments[' . $anonymous_comment3->id() . ']', 'Comment was deleted.');
    $this->drupalLogout();

    // Reset.
    user_role_change_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access comments' => FALSE,
      'post comments' => FALSE,
      'skip comment approval' => FALSE,
    ));

    // Attempt to view comments while disallowed.
    // NOTE: if authenticated user has permission to post comments, then a
    // "Login or register to post comments" type link may be shown.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertNoPattern('@<h2[^>]*>Comments</h2>@', 'Comments were not displayed.');
    $this->assertNoLink('Add new comment', 'Link to add comment was found.');

    // Attempt to view node-comment form while disallowed.
    $this->drupalGet('comment/reply/' . $this->node->id());
    $this->assertText('You are not authorized to post comments', 'Error attempting to post comment.');
    $this->assertNoFieldByName('subject', '', 'Subject field not found.');
    $this->assertNoFieldByName("comment_body[$langcode][0][value]", '', 'Comment field not found.');

    user_role_change_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access comments' => TRUE,
      'post comments' => FALSE,
      'skip comment approval' => FALSE,
    ));
    $this->drupalGet('node/' . $this->node->id());
    $this->assertPattern('@<h2[^>]*>Comments</h2>@', 'Comments were displayed.');
    $this->assertLink('Log in', 1, 'Link to log in was found.');
    $this->assertLink('register', 1, 'Link to register was found.');

    user_role_change_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access comments' => FALSE,
      'post comments' => TRUE,
      'skip comment approval' => TRUE,
    ));
    $this->drupalGet('node/' . $this->node->id());
    $this->assertNoPattern('@<h2[^>]*>Comments</h2>@', 'Comments were not displayed.');
    $this->assertFieldByName('subject', '', 'Subject field found.');
    $this->assertFieldByName("comment_body[$langcode][0][value]", '', 'Comment field found.');

    $this->drupalGet('comment/reply/' . $this->node->id() . '/' . $anonymous_comment3->id());
    $this->assertText('You are not authorized to view comments', 'Error attempting to post reply.');
    $this->assertNoText($author_name, 'Comment not displayed.');
  }
}
