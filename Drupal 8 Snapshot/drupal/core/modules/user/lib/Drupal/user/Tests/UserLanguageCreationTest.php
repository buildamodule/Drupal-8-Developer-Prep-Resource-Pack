<?php

/**
 * @file
 * Definition of Drupal\user\Tests\UserLanguageCreationTest.
 */

namespace Drupal\user\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;

/**
 * Functional test for language handling during user creation.
 */
class UserLanguageCreationTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('user', 'language');

  public static function getInfo() {
    return array(
      'name' => 'User language creation',
      'description' => 'Tests whether proper language is stored for new users and access to language selector.',
      'group' => 'User',
    );
  }

  /**
   * Functional test for language handling during user creation.
   */
  function testLocalUserCreation() {
    // User to add and remove language and create new users.
    $admin_user = $this->drupalCreateUser(array('administer languages', 'access administration pages', 'administer users'));
    $this->drupalLogin($admin_user);

    // Add predefined language.
    $langcode = 'fr';
    $language = new Language(array('id' => $langcode));
    language_save($language);

    // Set language negotiation.
    $edit = array(
      'language_interface[enabled][language-url]' => TRUE,
    );
    $this->drupalPost('admin/config/regional/language/detection', $edit, t('Save settings'));
    $this->assertText(t('Language negotiation configuration saved.'), 'Set language negotiation.');

    // Check if the language selector is available on admin/people/create and
    // set to the currently active language.
    $this->drupalGet($langcode . '/admin/people/create');
    $this->assertOptionSelected("edit-preferred-langcode", $langcode, 'Global language set in the language selector.');

    // Create a user with the admin/people/create form and check if the correct
    // language is set.
    $username = $this->randomName(10);
    $edit = array(
      'name' => $username,
      'mail' => $this->randomName(4) . '@example.com',
      'pass[pass1]' => $username,
      'pass[pass2]' => $username,
    );

    $this->drupalPost($langcode . '/admin/people/create', $edit, t('Create new account'));

    $user = user_load_by_name($username);
    $this->assertEqual($user->getPreferredLangcode(), $langcode, 'New user has correct preferred language set.');
    $this->assertEqual($user->language()->id, $langcode, 'New user has correct profile language set.');

    // Register a new user and check if the language selector is hidden.
    $this->drupalLogout();

    $this->drupalGet($langcode . '/user/register');
    $this->assertNoFieldByName('language[fr]', 'Language selector is not accessible.');

    $username = $this->randomName(10);
    $edit = array(
      'name' => $username,
      'mail' => $this->randomName(4) . '@example.com',
    );

    $this->drupalPost($langcode . '/user/register', $edit, t('Create new account'));

    $user = user_load_by_name($username);
    $this->assertEqual($user->getPreferredLangcode(), $langcode, 'New user has correct preferred language set.');
    $this->assertEqual($user->language()->id, $langcode, 'New user has correct profile language set.');

    // Test if the admin can use the language selector and if the
    // correct language is was saved.
    $user_edit = $langcode . '/user/' . $user->id() . '/edit';

    $this->drupalLogin($admin_user);
    $this->drupalGet($user_edit);
    $this->assertOptionSelected("edit-preferred-langcode", $langcode, 'Language selector is accessible and correct language is selected.');

    // Set pass_raw so we can login the new user.
    $user->pass_raw = $this->randomName(10);
    $edit = array(
      'pass[pass1]' => $user->pass_raw,
      'pass[pass2]' => $user->pass_raw,
    );

    $this->drupalPost($user_edit, $edit, t('Save'));

    $this->drupalLogin($user);
    $this->drupalGet($user_edit);
    $this->assertOptionSelected("edit-preferred-langcode", $langcode, 'Language selector is accessible and correct language is selected.');
  }
}
