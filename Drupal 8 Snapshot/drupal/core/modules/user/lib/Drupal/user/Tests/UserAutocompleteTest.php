<?php

/**
 * @file
 * Definition of Drupal\user\Tests\UserAutocompleteTest.
 */

namespace Drupal\user\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Test user autocompletion.
 */
class UserAutocompleteTest extends WebTestBase {
  public static function getInfo() {
    return array(
      'name' => 'User autocompletion',
      'description' => 'Test user autocompletion functionality.',
      'group' => 'User'
    );
  }

  function setUp() {
    parent::setUp();

    // Set up two users with different permissions to test access.
    $this->unprivileged_user = $this->drupalCreateUser();
    $this->privileged_user = $this->drupalCreateUser(array('access user profiles'));
  }

  /**
   * Tests access to user autocompletion and verify the correct results.
   */
  function testUserAutocomplete() {
    // Check access from unprivileged user, should be denied.
    $this->drupalLogin($this->unprivileged_user);
    $username = $this->unprivileged_user->getUsername();
    $this->drupalGet('user/autocomplete', array('query' => array('q' => $username[0])));
    $this->assertResponse(403, 'Autocompletion access denied to user without permission.');

    // Check access from privileged user.
    $this->drupalLogout();
    $this->drupalLogin($this->privileged_user);
    $this->drupalGet('user/autocomplete', array('query' => array('q' => $username[0])));
    $this->assertResponse(200, 'Autocompletion access allowed.');

    // Using first letter of the user's name, make sure the user's full name is in the results.
    $this->assertRaw($this->unprivileged_user->getUsername(), 'User name found in autocompletion results.');

    $anonymous_name = $this->randomString() . '<script>alert();</script>';
    \Drupal::config('user.settings')->set('anonymous', $anonymous_name)->save();
    // Test that anonymous username is in the result when requested and escaped
    // with check_plain().
    $users = $this->drupalGetJSON('user/autocomplete/anonymous', array('query' => array('q' => drupal_substr($anonymous_name, 0, 4))));
    $this->assertTrue(in_array(check_plain($anonymous_name), $users), 'The anonymous name found in autocompletion results.');
    $users = $this->drupalGetJSON('user/autocomplete', array('query' => array('q' => drupal_substr($anonymous_name, 0, 4))));
    $this->assertFalse(isset($users[$anonymous_name]), 'The anonymous name not found in autocompletion results without enabling anonymous username.');
  }
}
