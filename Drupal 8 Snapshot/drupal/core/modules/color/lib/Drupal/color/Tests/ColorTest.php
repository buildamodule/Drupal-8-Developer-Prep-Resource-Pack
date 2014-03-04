<?php

/**
 * @file
 * Definition of Drupal\color\Tests\ColorTest.
 */

namespace Drupal\color\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the Color module functionality.
 */
class ColorTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('color');

  protected $big_user;
  protected $themes;
  protected $colorTests;

  public static function getInfo() {
    return array(
      'name' => 'Color functionality',
      'description' => 'Modify the Bartik theme colors and make sure the changes are reflected on the frontend.',
      'group' => 'Color',
    );
  }

  function setUp() {
    parent::setUp();

    // Create users.
    $this->big_user = $this->drupalCreateUser(array('administer themes'));

    // This tests the color module in Bartik.
    $this->themes = array(
      'bartik' => array(
        'palette_input' => 'palette[bg]',
        'scheme' => 'slate',
        'scheme_color' => '#3b3b3b',
      ),
    );
    theme_enable(array_keys($this->themes));
    $this->container->get('router.builder')->rebuild();
    menu_router_rebuild();

    // Array filled with valid and not valid color values
    $this->colorTests = array(
      '#000' => TRUE,
      '#123456' => TRUE,
      '#abcdef' => TRUE,
      '#0' => FALSE,
      '#00' => FALSE,
      '#0000' => FALSE,
      '#00000' => FALSE,
      '123456' => FALSE,
      '#00000g' => FALSE,
    );
  }

  /**
   * Tests the Color module functionality.
   */
  function testColor() {
    foreach ($this->themes as $theme => $test_values) {
      $this->_testColor($theme, $test_values);
    }
  }

  /**
   * Tests the Color module functionality using the given theme.
   */
  function _testColor($theme, $test_values) {
    \Drupal::config('system.theme')
      ->set('default', $theme)
      ->save();
    $settings_path = 'admin/appearance/settings/' . $theme;

    $this->drupalLogin($this->big_user);
    $this->drupalGet($settings_path);
    $this->assertResponse(200);
    $edit['scheme'] = '';
    $edit[$test_values['palette_input']] = '#123456';
    $this->drupalPost($settings_path, $edit, t('Save configuration'));

    $this->drupalGet('<front>');
    $stylesheets = \Drupal::config('color.' . $theme)->get('stylesheets');
    $this->assertPattern('|' . file_create_url($stylesheets[0]) . '|', 'Make sure the color stylesheet is included in the content. (' . $theme . ')');

    $stylesheet_content = join("\n", file($stylesheets[0]));
    $this->assertTrue(strpos($stylesheet_content, 'color: #123456') !== FALSE, 'Make sure the color we changed is in the color stylesheet. (' . $theme . ')');

    $this->drupalGet($settings_path);
    $this->assertResponse(200);
    $edit['scheme'] = $test_values['scheme'];
    $this->drupalPost($settings_path, $edit, t('Save configuration'));

    $this->drupalGet('<front>');
    $stylesheets = \Drupal::config('color.' . $theme)->get('stylesheets');
    $stylesheet_content = join("\n", file($stylesheets[0]));
    $this->assertTrue(strpos($stylesheet_content, 'color: ' . $test_values['scheme_color']) !== FALSE, 'Make sure the color we changed is in the color stylesheet. (' . $theme . ')');

    // Test with aggregated CSS turned on.
    $config = \Drupal::config('system.performance');
    $config->set('css.preprocess', 1);
    $config->save();
    $this->drupalGet('<front>');
    $stylesheets = \Drupal::state()->get('drupal_css_cache_files') ?: array();
    $stylesheet_content = '';
    foreach ($stylesheets as $key => $uri) {
      $stylesheet_content .= join("\n", file(drupal_realpath($uri)));
    }
    $this->assertTrue(strpos($stylesheet_content, 'public://') === FALSE, 'Make sure the color paths have been translated to local paths. (' . $theme . ')');
    $config->set('css.preprocess', 0);
    $config->save();
  }

  /**
   * Tests whether the provided color is valid.
   */
  function testValidColor() {
    \Drupal::config('system.theme')
      ->set('default', 'bartik')
      ->save();
    $settings_path = 'admin/appearance/settings/bartik';

    $this->drupalLogin($this->big_user);
    $edit['scheme'] = '';

    foreach ($this->colorTests as $color => $is_valid) {
      $edit['palette[bg]'] = $color;
      $this->drupalPost($settings_path, $edit, t('Save configuration'));

      if($is_valid) {
        $this->assertText('The configuration options have been saved.');
      }
      else {
        $this->assertText('You must enter a valid hexadecimal color value for Main background.');
      }
    }
  }
}
