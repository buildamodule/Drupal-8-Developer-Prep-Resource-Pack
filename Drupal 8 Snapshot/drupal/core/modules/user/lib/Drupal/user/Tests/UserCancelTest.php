<?php

/**
 * @file
 * Definition of Drupal\user\Tests\UserCancelTest.
 */

namespace Drupal\user\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;

/**
 * Test cancelling a user.
 */
class UserCancelTest extends WebTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Cancel account',
      'description' => 'Ensure that account cancellation methods work as expected.',
      'group' => 'User',
    );
  }

  function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
  }

  /**
   * Attempt to cancel account without permission.
   */
  function testUserCancelWithoutPermission() {
    \Drupal::config('user.settings')->set('cancel_method', 'user_cancel_reassign')->save();

    // Create a user.
    $account = $this->drupalCreateUser(array());
    $this->drupalLogin($account);
    // Load real user object.
    $account = user_load($account->id(), TRUE);

    // Create a node.
    $node = $this->drupalCreateNode(array('uid' => $account->id()));

    // Attempt to cancel account.
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->assertNoRaw(t('Cancel account'), 'No cancel account button displayed.');

    // Attempt bogus account cancellation request confirmation.
    $timestamp = $account->getLastLoginTime();
    $this->drupalGet("user/" . $account->id() . "/cancel/confirm/$timestamp/" . user_pass_rehash($account->getPassword(), $timestamp, $account->getLastLoginTime()));
    $this->assertResponse(403, 'Bogus cancelling request rejected.');
    $account = user_load($account->id());
    $this->assertTrue($account->isActive(), 'User account was not canceled.');

    // Confirm user's content has not been altered.
    $test_node = node_load($node->id(), TRUE);
    $this->assertTrue(($test_node->uid == $account->id() && $test_node->status == 1), 'Node of the user has not been altered.');
  }

  /**
   * Tests that user account for uid 1 cannot be cancelled.
   *
   * This should never be possible, or the site owner would become unable to
   * administer the site.
   */
  function testUserCancelUid1() {
    module_enable(array('views'));
    // Update uid 1's name and password to we know it.
    $password = user_password();
    $account = array(
      'name' => 'user1',
      'pass' => drupal_container()->get('password')->hash(trim($password)),
    );
    // We cannot use $account->save() here, because this would result in the
    // password being hashed again.
    db_update('users')
      ->fields($account)
      ->condition('uid', 1)
      ->execute();

    // Reload and log in uid 1.
    $user1 = user_load(1, TRUE);
    $user1->pass_raw = $password;

    // Try to cancel uid 1's account with a different user.
    $this->admin_user = $this->drupalCreateUser(array('administer users'));
    $this->drupalLogin($this->admin_user);
    $edit = array(
      'action' => 'user_cancel_user_action',
      'user_bulk_form[0]' => TRUE,
    );
    $this->drupalPost('admin/people', $edit, t('Apply'));

    // Verify that uid 1's account was not cancelled.
    $user1 = user_load(1, TRUE);
    $this->assertTrue($user1->isActive(), 'User #1 still exists and is not blocked.');
  }

  /**
   * Attempt invalid account cancellations.
   */
  function testUserCancelInvalid() {
    \Drupal::config('user.settings')->set('cancel_method', 'user_cancel_reassign')->save();

    // Create a user.
    $account = $this->drupalCreateUser(array('cancel account'));
    $this->drupalLogin($account);
    // Load real user object.
    $account = user_load($account->id(), TRUE);

    // Create a node.
    $node = $this->drupalCreateNode(array('uid' => $account->id()));

    // Attempt to cancel account.
    $this->drupalPost('user/' . $account->id() . '/edit', NULL, t('Cancel account'));

    // Confirm account cancellation.
    $timestamp = time();
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertText(t('A confirmation request to cancel your account has been sent to your e-mail address.'), 'Account cancellation request mailed message displayed.');

    // Attempt bogus account cancellation request confirmation.
    $bogus_timestamp = $timestamp + 60;
    $this->drupalGet("user/" . $account->id() . "/cancel/confirm/$bogus_timestamp/" . user_pass_rehash($account->getPassword(), $bogus_timestamp, $account->getLastLoginTime()));
    $this->assertText(t('You have tried to use an account cancellation link that has expired. Please request a new one using the form below.'), 'Bogus cancelling request rejected.');
    $account = user_load($account->id());
    $this->assertTrue($account->isActive(), 'User account was not canceled.');

    // Attempt expired account cancellation request confirmation.
    $bogus_timestamp = $timestamp - 86400 - 60;
    $this->drupalGet("user/" . $account->id() . "/cancel/confirm/$bogus_timestamp/" . user_pass_rehash($account->getPassword(), $bogus_timestamp, $account->getLastLoginTime()));
    $this->assertText(t('You have tried to use an account cancellation link that has expired. Please request a new one using the form below.'), 'Expired cancel account request rejected.');
    $account = user_load($account->id(), TRUE);
    $this->assertTrue($account->isActive(), 'User account was not canceled.');

    // Confirm user's content has not been altered.
    $test_node = node_load($node->id(), TRUE);
    $this->assertTrue(($test_node->uid == $account->id() && $test_node->status == 1), 'Node of the user has not been altered.');
  }

  /**
   * Disable account and keep all content.
   */
  function testUserBlock() {
    \Drupal::config('user.settings')->set('cancel_method', 'user_cancel_block')->save();

    // Create a user.
    $web_user = $this->drupalCreateUser(array('cancel account'));
    $this->drupalLogin($web_user);

    // Load real user object.
    $account = user_load($web_user->id(), TRUE);

    // Attempt to cancel account.
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertText(t('Are you sure you want to cancel your account?'), 'Confirmation form to cancel account displayed.');
    $this->assertText(t('Your account will be blocked and you will no longer be able to log in. All of your content will remain attributed to your user name.'), 'Informs that all content will be remain as is.');
    $this->assertNoText(t('Select the method to cancel the account above.'), 'Does not allow user to select account cancellation method.');

    // Confirm account cancellation.
    $timestamp = time();

    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertText(t('A confirmation request to cancel your account has been sent to your e-mail address.'), 'Account cancellation request mailed message displayed.');

    // Confirm account cancellation request.
    $this->drupalGet("user/" . $account->id() . "/cancel/confirm/$timestamp/" . user_pass_rehash($account->getPassword(), $timestamp, $account->getLastLoginTime()));
    $account = user_load($account->id(), TRUE);
    $this->assertTrue($account->isBlocked(), 'User has been blocked.');

    // Confirm that the confirmation message made it through to the end user.
    $this->assertRaw(t('%name has been disabled.', array('%name' => $account->getUsername())), "Confirmation message displayed to user.");
  }

  /**
   * Disable account and unpublish all content.
   */
  function testUserBlockUnpublish() {
    \Drupal::config('user.settings')->set('cancel_method', 'user_cancel_block_unpublish')->save();

    // Create a user.
    $account = $this->drupalCreateUser(array('cancel account'));
    $this->drupalLogin($account);
    // Load real user object.
    $account = user_load($account->id(), TRUE);

    // Create a node with two revisions.
    $node = $this->drupalCreateNode(array('uid' => $account->id()));
    $settings = get_object_vars($node);
    $settings['revision'] = 1;
    $node = $this->drupalCreateNode($settings);

    // Attempt to cancel account.
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertText(t('Are you sure you want to cancel your account?'), 'Confirmation form to cancel account displayed.');
    $this->assertText(t('Your account will be blocked and you will no longer be able to log in. All of your content will be hidden from everyone but administrators.'), 'Informs that all content will be unpublished.');

    // Confirm account cancellation.
    $timestamp = time();
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertText(t('A confirmation request to cancel your account has been sent to your e-mail address.'), 'Account cancellation request mailed message displayed.');

    // Confirm account cancellation request.
    $this->drupalGet("user/" . $account->id() . "/cancel/confirm/$timestamp/" . user_pass_rehash($account->getPassword(), $timestamp, $account->getLastLoginTime()));
    $account = user_load($account->id(), TRUE);
    $this->assertTrue($account->isBlocked(), 'User has been blocked.');

    // Confirm user's content has been unpublished.
    $test_node = node_load($node->id(), TRUE);
    $this->assertTrue($test_node->status == 0, 'Node of the user has been unpublished.');
    $test_node = node_revision_load($node->vid);
    $this->assertTrue($test_node->status == 0, 'Node revision of the user has been unpublished.');

    // Confirm that the confirmation message made it through to the end user.
    $this->assertRaw(t('%name has been disabled.', array('%name' => $account->getUsername())), "Confirmation message displayed to user.");
  }

  /**
   * Delete account and anonymize all content.
   */
  function testUserAnonymize() {
    \Drupal::config('user.settings')->set('cancel_method', 'user_cancel_reassign')->save();

    // Create a user.
    $account = $this->drupalCreateUser(array('cancel account'));
    $this->drupalLogin($account);
    // Load real user object.
    $account = user_load($account->id(), TRUE);

    // Create a simple node.
    $node = $this->drupalCreateNode(array('uid' => $account->id()));

    // Create a node with two revisions, the initial one belonging to the
    // cancelling user.
    $revision_node = $this->drupalCreateNode(array('uid' => $account->id()));
    $revision = $revision_node->vid;
    $settings = get_object_vars($revision_node);
    $settings['revision'] = 1;
    $settings['uid'] = 1; // Set new/current revision to someone else.
    $revision_node = $this->drupalCreateNode($settings);

    // Attempt to cancel account.
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertText(t('Are you sure you want to cancel your account?'), 'Confirmation form to cancel account displayed.');
    $this->assertRaw(t('Your account will be removed and all account information deleted. All of your content will be assigned to the %anonymous-name user.', array('%anonymous-name' => \Drupal::config('user.settings')->get('anonymous'))), 'Informs that all content will be attributed to anonymous account.');

    // Confirm account cancellation.
    $timestamp = time();
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertText(t('A confirmation request to cancel your account has been sent to your e-mail address.'), 'Account cancellation request mailed message displayed.');

    // Confirm account cancellation request.
    $this->drupalGet("user/" . $account->id() . "/cancel/confirm/$timestamp/" . user_pass_rehash($account->getPassword(), $timestamp, $account->getLastLoginTime()));
    $this->assertFalse(user_load($account->id(), TRUE), 'User is not found in the database.');

    // Confirm that user's content has been attributed to anonymous user.
    $test_node = node_load($node->id(), TRUE);
    $this->assertTrue(($test_node->uid == 0 && $test_node->status == 1), 'Node of the user has been attributed to anonymous user.');
    $test_node = node_revision_load($revision, TRUE);
    $this->assertTrue(($test_node->revision_uid == 0 && $test_node->status == 1), 'Node revision of the user has been attributed to anonymous user.');
    $test_node = node_load($revision_node->id(), TRUE);
    $this->assertTrue(($test_node->uid != 0 && $test_node->status == 1), "Current revision of the user's node was not attributed to anonymous user.");

    // Confirm that the confirmation message made it through to the end user.
    $this->assertRaw(t('%name has been deleted.', array('%name' => $account->getUsername())), "Confirmation message displayed to user.");
  }

  /**
   * Delete account and remove all content.
   */
  function testUserDelete() {
    \Drupal::config('user.settings')->set('cancel_method', 'user_cancel_delete')->save();
    module_enable(array('comment'));
    $this->resetAll();

    // Create a user.
    $account = $this->drupalCreateUser(array('cancel account', 'post comments', 'skip comment approval'));
    $this->drupalLogin($account);
    // Load real user object.
    $account = user_load($account->id(), TRUE);

    // Create a simple node.
    $node = $this->drupalCreateNode(array('uid' => $account->id()));

    // Create comment.
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $edit = array();
    $edit['subject'] = $this->randomName(8);
    $edit['comment_body[' . $langcode . '][0][value]'] = $this->randomName(16);

    $this->drupalPost('comment/reply/' . $node->id(), $edit, t('Preview'));
    $this->drupalPost(NULL, array(), t('Save'));
    $this->assertText(t('Your comment has been posted.'));
    $comments = entity_load_multiple_by_properties('comment', array('subject' => $edit['subject']));
    $comment = reset($comments);
    $this->assertTrue($comment->id(), 'Comment found.');

    // Create a node with two revisions, the initial one belonging to the
    // cancelling user.
    $revision_node = $this->drupalCreateNode(array('uid' => $account->id()));
    $revision = $revision_node->vid;
    $settings = get_object_vars($revision_node);
    $settings['revision'] = 1;
    $settings['uid'] = 1; // Set new/current revision to someone else.
    $revision_node = $this->drupalCreateNode($settings);

    // Attempt to cancel account.
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertText(t('Are you sure you want to cancel your account?'), 'Confirmation form to cancel account displayed.');
    $this->assertText(t('Your account will be removed and all account information deleted. All of your content will also be deleted.'), 'Informs that all content will be deleted.');

    // Confirm account cancellation.
    $timestamp = time();
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertText(t('A confirmation request to cancel your account has been sent to your e-mail address.'), 'Account cancellation request mailed message displayed.');

    // Confirm account cancellation request.
    $this->drupalGet("user/" . $account->id() . "/cancel/confirm/$timestamp/" . user_pass_rehash($account->getPassword(), $timestamp, $account->getLastLoginTime()));
    $this->assertFalse(user_load($account->id(), TRUE), 'User is not found in the database.');

    // Confirm that user's content has been deleted.
    $this->assertFalse(node_load($node->id(), TRUE), 'Node of the user has been deleted.');
    $this->assertFalse(node_revision_load($revision), 'Node revision of the user has been deleted.');
    $this->assertTrue(node_load($revision_node->id(), TRUE), "Current revision of the user's node was not deleted.");
    $this->assertFalse(comment_load($comment->id(), TRUE), 'Comment of the user has been deleted.');

    // Confirm that the confirmation message made it through to the end user.
    $this->assertRaw(t('%name has been deleted.', array('%name' => $account->getUsername())), "Confirmation message displayed to user.");
  }

  /**
   * Create an administrative user and delete another user.
   */
  function testUserCancelByAdmin() {
    \Drupal::config('user.settings')->set('cancel_method', 'user_cancel_reassign')->save();

    // Create a regular user.
    $account = $this->drupalCreateUser(array());

    // Create administrative user.
    $admin_user = $this->drupalCreateUser(array('administer users'));
    $this->drupalLogin($admin_user);

    // Delete regular user.
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertRaw(t('Are you sure you want to cancel the account %name?', array('%name' => $account->getUsername())), 'Confirmation form to cancel account displayed.');
    $this->assertText(t('Select the method to cancel the account above.'), 'Allows to select account cancellation method.');

    // Confirm deletion.
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertRaw(t('%name has been deleted.', array('%name' => $account->getUsername())), 'User deleted.');
    $this->assertFalse(user_load($account->id()), 'User is not found in the database.');
  }

  /**
   * Tests deletion of a user account without an e-mail address.
   */
  function testUserWithoutEmailCancelByAdmin() {
    \Drupal::config('user.settings')->set('cancel_method', 'user_cancel_reassign')->save();

    // Create a regular user.
    $account = $this->drupalCreateUser(array());
    // This user has no e-mail address.
    $account->mail = '';
    $account->save();

    // Create administrative user.
    $admin_user = $this->drupalCreateUser(array('administer users'));
    $this->drupalLogin($admin_user);

    // Delete regular user without e-mail address.
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertRaw(t('Are you sure you want to cancel the account %name?', array('%name' => $account->getUsername())), 'Confirmation form to cancel account displayed.');
    $this->assertText(t('Select the method to cancel the account above.'), 'Allows to select account cancellation method.');

    // Confirm deletion.
    $this->drupalPost(NULL, NULL, t('Cancel account'));
    $this->assertRaw(t('%name has been deleted.', array('%name' => $account->getUsername())), 'User deleted.');
    $this->assertFalse(user_load($account->id()), 'User is not found in the database.');
  }

  /**
   * Create an administrative user and mass-delete other users.
   */
  function testMassUserCancelByAdmin() {
    module_enable(array('views'));
    \Drupal::config('user.settings')->set('cancel_method', 'user_cancel_reassign')->save();
    // Enable account cancellation notification.
    \Drupal::config('user.settings')->set('notify.status_canceled', TRUE)->save();

    // Create administrative user.
    $admin_user = $this->drupalCreateUser(array('administer users'));
    $this->drupalLogin($admin_user);

    // Create some users.
    $users = array();
    for ($i = 0; $i < 3; $i++) {
      $account = $this->drupalCreateUser(array());
      $users[$account->id()] = $account;
    }

    // Cancel user accounts, including own one.
    $edit = array();
    $edit['action'] = 'user_cancel_user_action';
    for ($i = 0; $i <= 4; $i++) {
      $edit['user_bulk_form[' . $i . ']'] = TRUE;
    }
    $this->drupalPost('admin/people', $edit, t('Apply'));
    $this->assertText(t('Are you sure you want to cancel these user accounts?'), 'Confirmation form to cancel accounts displayed.');
    $this->assertText(t('When cancelling these accounts'), 'Allows to select account cancellation method.');
    $this->assertText(t('Require e-mail confirmation to cancel account.'), 'Allows to send confirmation mail.');
    $this->assertText(t('Notify user when account is canceled.'), 'Allows to send notification mail.');

    // Confirm deletion.
    $this->drupalPost(NULL, NULL, t('Cancel accounts'));
    $status = TRUE;
    foreach ($users as $account) {
      $status = $status && (strpos($this->content, t('%name has been deleted.', array('%name' => $account->getUsername()))) !== FALSE);
      $status = $status && !user_load($account->id(), TRUE);
    }
    $this->assertTrue($status, 'Users deleted and not found in the database.');

    // Ensure that admin account was not cancelled.
    $this->assertText(t('A confirmation request to cancel your account has been sent to your e-mail address.'), 'Account cancellation request mailed message displayed.');
    $admin_user = user_load($admin_user->id());
    $this->assertTrue($admin_user->isActive(), 'Administrative user is found in the database and enabled.');

    // Verify that uid 1's account was not cancelled.
    $user1 = user_load(1, TRUE);
    $this->assertTrue($user1->isActive(), 'User #1 still exists and is not blocked.');
  }
}
