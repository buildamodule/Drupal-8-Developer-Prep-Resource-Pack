<?php

/**
 * @file
 * Definition of Drupal\search\Tests\SearchKeywordsConditionsTest.
 */

namespace Drupal\search\Tests;

/**
 * Tests keywords and conditions.
 */
class SearchKeywordsConditionsTest extends SearchTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('comment', 'search_extra_type');

  public static function getInfo() {
    return array(
      'name' => 'Keywords and conditions',
      'description' => 'Verify the search pulls in keywords and extra conditions.',
      'group' => 'Search',
    );
  }

  function setUp() {
    parent::setUp();

    // Create searching user.
    $this->searching_user = $this->drupalCreateUser(array('search content', 'access content', 'access comments', 'skip comment approval'));
    // Login with sufficient privileges.
    $this->drupalLogin($this->searching_user);
    // Test with all search modules enabled.
    \Drupal::config('search.settings')->set('active_modules', array('node' => 'node', 'user' => 'user', 'search_extra_type' => 'search_extra_type'))->save();
    menu_router_rebuild();
  }

  /**
   * Verify the kewords are captured and conditions respected.
   */
  function testSearchKeyswordsConditions() {
    // No keys, not conditions - no results.
    $this->drupalGet('search/dummy_path');
    $this->assertNoText('Dummy search snippet to display');
    // With keys - get results.
    $keys = 'bike shed ' . $this->randomName();
    $this->drupalGet("search/dummy_path/{$keys}");
    $this->assertText("Dummy search snippet to display. Keywords: {$keys}");
    $keys = 'blue drop ' . $this->randomName();
    $this->drupalGet("search/dummy_path", array('query' => array('keys' => $keys)));
    $this->assertText("Dummy search snippet to display. Keywords: {$keys}");
    // Add some conditions and keys.
    $keys = 'moving drop ' . $this->randomName();
    $this->drupalGet("search/dummy_path/bike", array('query' => array('search_conditions' => $keys)));
    $this->assertText("Dummy search snippet to display.");
    $this->assertRaw(print_r(array('search_conditions' => $keys), TRUE));
    // Add some conditions and no keys.
    $keys = 'drop kick ' . $this->randomName();
    $this->drupalGet("search/dummy_path", array('query' => array('search_conditions' => $keys)));
    $this->assertText("Dummy search snippet to display.");
    $this->assertRaw(print_r(array('search_conditions' => $keys), TRUE));
  }
}
