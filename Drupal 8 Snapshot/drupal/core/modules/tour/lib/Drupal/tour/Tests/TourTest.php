<?php

/**
 * @file
 * Contains \Drupal\tour\Tests\TourTest.
 */

namespace Drupal\tour\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;

/**
 * Tests tour functionality.
 */
class TourTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('tour', 'locale', 'language', 'tour_test');

  public static function getInfo() {
    return array(
      'name' => 'Tour tests',
      'description' => 'Test the functionality of tour tips.',
      'group' => 'Tour',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser(array('access tour', 'administer languages')));
  }

  /**
   * Test tour functionality.
   */
  public function testTourFunctionality() {
    // Navigate to tour-test-1 and verify the tour_test_1 tip is found with appropriate classes.
    $this->drupalGet('tour-test-1');
    $elements = $this->xpath('//li[@data-id=:data_id and @class=:classes and ./h2[contains(., :text)]]', array(
      ':classes' => 'tip-module-tour-test tip-type-text tip-tour-test-1 even last',
      ':data_id' => 'tour-test-1',
      ':text' => 'The first tip',
    ));
    $this->assertEqual(count($elements), 1, 'Found English variant of tip 1.');

    $elements = $this->xpath('//li[@data-id=:data_id and @class=:classes and ./p//a[@href=:href and contains(., :text)]]', array(
      ':classes' => 'tip-module-tour-test tip-type-text tip-tour-test-1 even last',
      ':data_id' => 'tour-test-1',
      ':href' =>  url('<front>', array('absolute' => TRUE)),
      ':text' => 'Drupal',
    ));
    $this->assertEqual(count($elements), 1, 'Found Token replacement.');

    $elements = $this->xpath('//li[@data-id=:data_id and ./h2[contains(., :text)]]', array(
      ':data_id' => 'tour-test-2',
      ':text' => 'The quick brown fox',
    ));
    $this->assertNotEqual(count($elements), 1, 'Did not find English variant of tip 2.');

    $elements = $this->xpath('//li[@data-id=:data_id and ./h2[contains(., :text)]]', array(
      ':data_id' => 'tour-test-1',
      ':text' => 'La pioggia cade in spagna',
    ));
    $this->assertNotEqual(count($elements), 1, 'Did not find Italian variant of tip 1.');

    // Ensure that plugin's work.
    $this->assertRaw('img src="http://local/image.png"', 'Image plugin tip found.');

    // Navigate to tour-test-2/subpath and verify the tour_test_2 tip is found.
    $this->drupalGet('tour-test-2/subpath');
    $elements = $this->xpath('//li[@data-id=:data_id and ./h2[contains(., :text)]]', array(
      ':data_id' => 'tour-test-2',
      ':text' => 'The quick brown fox',
    ));
    $this->assertEqual(count($elements), 1, 'Found English variant of tip 2.');

    $elements = $this->xpath('//li[@data-id=:data_id and ./h2[contains(., :text)]]', array(
      ':data_id' => 'tour-test-1',
      ':text' => 'The first tip',
    ));
    $this->assertNotEqual(count($elements), 1, 'Did not find English variant of tip 1.');

    // Enable Italian language and navigate to it/tour-test1 and verify italian
    // version of tip is found.
    language_save(new Language(array('id' => 'it')));
    $this->drupalGet('it/tour-test-1');

    $elements = $this->xpath('//li[@data-id=:data_id and ./h2[contains(., :text)]]', array(
      ':data_id' => 'tour-test-1',
      ':text' => 'La pioggia cade in spagna',
    ));
    $this->assertEqual(count($elements), 1, 'Found Italian variant of tip 1.');

    $elements = $this->xpath('//li[@data-id=:data_id and ./h2[contains(., :text)]]', array(
      ':data_id' => 'tour-test-1',
      ':text' => 'The first tip',
    ));
    $this->assertNotEqual(count($elements), 1, 'Did not find English variant of tip 1.');

    language_save(new Language(array('id' => 'en')));

    // Programmatically create a tour for use through the remainder of the test.
    entity_create('tour', array(
      'id' => 'tour-entity-create-test-en',
      'label' => 'Tour test english',
      'langcode' => 'en',
      'paths' => array(
        'tour-test-1',
      ),
      'tips' => array(
        'tour-test-1' => array(
          'id' => 'tour-code-test-1',
          'plugin' => 'text',
          'label' => 'The rain in spain',
          'body' => 'Falls mostly on the plain.',
          'weight' => '100',
          'attributes' => array(
            'data-id' => 'tour-code-test-1',
          ),
        ),
      ),
    ))->save();

    $this->drupalGet('tour-test-1');

    // Load it back from the database and verify storage worked.
    $entity_save_tip = entity_load('tour', 'tour-entity-create-test-en');
    // Verify that hook_ENTITY_TYPE_load() integration worked.
    $this->assertEqual($entity_save_tip->loaded, 'Load hooks work');
    // Verify that hook_ENTITY_TYPE_presave() integration worked.
    $this->assertEqual($entity_save_tip->label(), 'Tour test english alter');

    // Navigate to tour-test-1 and verify the new tip is found.
    $this->drupalGet('tour-test-1');
    $elements = $this->xpath('//li[@data-id=:data_id and ./h2[contains(., :text)]]', array(
      ':data_id' => 'tour-code-test-1',
      ':text' => 'The rain in spain',
    ));
    $this->assertEqual(count($elements), 1, 'Found the required tip markup for tip 4');

    // Verify that the weight sorting works by ensuring the lower weight item
    // (tip 4) has the 'End tour' button.
    $elements = $this->xpath('//li[@data-id=:data_id and @data-text=:text]', array(
      ':data_id' => 'tour-code-test-1',
      ':text' => 'End tour',
    ));
    $this->assertEqual(count($elements), 1, 'Found code tip was weighted last and had "End tour".');

    // Test hook_tour_alter().
    $this->assertText('Altered by hook_tour_tips_alter');
  }
}
