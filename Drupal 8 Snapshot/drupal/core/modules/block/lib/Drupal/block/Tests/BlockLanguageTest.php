<?php

/**
 * @file
 * Definition of Drupal\block\Tests\BlockLanguageTest.
 */

namespace Drupal\block\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Functional tests for the language list configuration forms.
 */
class BlockLanguageTest extends WebTestBase {

  /**
   * An administrative user to configure the test environment.
   */
  protected $adminUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('language', 'block');

  public static function getInfo() {
    return array(
      'name' => 'Language block visibility',
      'description' => 'Tests if a block can be configure to be only visibile on a particular language.',
      'group' => 'Block',
    );
  }

  function setUp() {
    parent::setUp();

    // Create a new user, allow him to manage the blocks and the languages.
    $this->adminUser = $this->drupalCreateUser(array('administer blocks', 'administer languages', 'administer site configuration'));
    $this->drupalLogin($this->adminUser);

    // Add predefined language.
    $edit = array(
      'predefined_langcode' => 'fr',
    );
    $this->drupalPost('admin/config/regional/language/add', $edit, t('Add language'));
    $this->assertText('French', 'Language added successfully.');
  }

  /**
   * Tests the visibility settings for the blocks based on language.
   */
  public function testLanguageBlockVisibility() {
    // Check if the visibility setting is available.
    $default_theme = \Drupal::config('system.theme')->get('default');
    $this->drupalGet('admin/structure/block/add/system_powered_by_block' . '/' . $default_theme);

    $this->assertField('visibility[language][langcodes][en]', 'Language visibility field is visible.');

    // Enable a standard block and set the visibility setting for one language.
    $edit = array(
      'visibility[language][langcodes][en]' => TRUE,
      'machine_name' => strtolower($this->randomName(8)),
      'region' => 'sidebar_first',
    );
    $this->drupalPost('admin/structure/block/add/system_powered_by_block' . '/' . $default_theme, $edit, t('Save block'));

    // Change the default language.
    $edit = array(
      'site_default_language' => 'fr',
    );
    $this->drupalpost('admin/config/regional/settings', $edit, t('Save configuration'));

    // Reset the static cache of the language list.
    drupal_static_reset('language_list');

    // Check that a page has a block.
    $this->drupalget('', array('language' => language_load('en')));
    $this->assertText('Powered by Drupal', 'The body of the custom block appears on the page.');

    // Check that a page doesn't has a block for the current language anymore.
    $this->drupalGet('', array('language' => language_load('fr')));
    $this->assertNoText('Powered by Drupal', 'The body of the custom block does not appear on the page.');
  }

  /**
   * Tests if the visibility settings are removed if the language is deleted.
   */
  public function testLanguageBlockVisibilityLanguageDelete() {
    // Enable a standard block and set the visibility setting for one language.
    $edit = array(
      'visibility' => array(
        'language' => array(
          'language_type' => 'language_interface',
          'langcodes' => array(
            'fr' => 'fr',
          ),
        ),
      ),
      'machine_name' => 'language_block_test',
    );
    $block = $this->drupalPlaceBlock('system_powered_by_block', $edit);

    // Check that we have the language in config after saving the setting.
    $visibility = $block->get('visibility');
    $language = $visibility['language']['langcodes']['fr'];
    $this->assertTrue('fr' === $language, 'Language is set in the block configuration.');

    // Delete the language.
    $this->drupalPost('admin/config/regional/language/delete/fr', array(), t('Delete'));

    // Check that the language is no longer stored in the configuration after
    // it is deleted.
    $block = entity_load('block', $block->id());
    $visibility = $block->get('visibility');
    $this->assertTrue(empty($visibility['language']['langcodes']['fr']), 'Language is no longer not set in the block configuration after deleting the block.');
  }

}
