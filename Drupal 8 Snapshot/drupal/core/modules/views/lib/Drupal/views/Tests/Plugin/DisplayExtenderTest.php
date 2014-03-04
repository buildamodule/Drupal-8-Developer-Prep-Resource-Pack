<?php

  /**
   * @file
   * Definition of Drupal\views\Tests\Plugin\DisplayExtenderTest.
   */

namespace Drupal\views\Tests\Plugin;

use Drupal\views\Tests\Plugin\PluginTestBase;

/**
 * Tests the display extender plugins.
 *
 * @see Drupal\views_test_data\Plugin\views\display_extender\DisplayExtenderTest
 */
class DisplayExtenderTest extends PluginTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_view');

  public static function getInfo() {
    return array(
      'name' => 'Display extender',
      'description' => 'Tests the display extender plugins.',
      'group' => 'Views Plugins',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->enableViewsTestModule();
  }

  /**
   * Test display extenders.
   */
  public function testDisplayExtenders() {
    \Drupal::config('views.settings')->set('display_extenders', array('display_extender_test'))->save();
    $this->assertEqual(count(views_get_enabled_display_extenders()), 1, 'Make sure that there is only one enabled display extender.');

    $view = views_get_view('test_view');
    $view->initDisplay();

    $this->assertEqual(count($view->display_handler->extender), 1, 'Make sure that only one extender is initialized.');

    $display_extender = $view->display_handler->extender['display_extender_test'];
    $this->assertTrue($display_extender instanceof \Drupal\views_test_data\Plugin\views\display_extender\DisplayExtenderTest, 'Make sure the right class got initialized.');

    $view->preExecute();
    $this->assertTrue($display_extender->testState['preExecute'], 'Make sure the display extender was able to react on preExecute.');
    $view->execute();
    $this->assertTrue($display_extender->testState['query'], 'Make sure the display extender was able to react on query.');
  }

}
