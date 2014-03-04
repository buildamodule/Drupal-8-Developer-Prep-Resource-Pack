<?php

/**
 * @file
 * Contains \Drupal\system\Tests\Menu\LocalActionTest.
 */

namespace Drupal\system\Tests\Menu;

use Drupal\simpletest\WebTestBase;

/**
 * Tests local actions.
 */
class LocalActionTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array('menu_test');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Local actions',
      'description' => 'Tests local actions derived from router and added/altered via hooks.',
      'group' => 'Menu',
    );
  }

  /**
   * Tests appearance of local actions.
   */
  public function testLocalAction() {
    $this->drupalGet('menu-test-local-action');
    // Ensure that both menu and route based actions are shown.
    $this->assertLocalAction(array(
      'menu-test-local-action/dynamic-title' => 'My dynamic-title action',
      'menu-test-local-action/hook_menu' => 'My hook_menu action',
      'menu-test-local-action/routing' => 'My routing action',
      'menu-test-local-action/routing' => 'My YAML discovery action',
    ));
  }

  /**
   * Asserts local actions in the page output.
   *
   * @param array $actions
   *   A list of expected action link titles, keyed by the hrefs.
   */
  protected function assertLocalAction(array $actions) {
    $elements = $this->xpath('//a[contains(@class, :class)]', array(
      ':class' => 'button-action',
    ));
    $index = 0;
    foreach ($actions as $href => $title) {
      $this->assertEqual((string) $elements[$index], $title);
      $this->assertEqual($elements[$index]['href'], url($href));
      $index++;
    }
  }

}
