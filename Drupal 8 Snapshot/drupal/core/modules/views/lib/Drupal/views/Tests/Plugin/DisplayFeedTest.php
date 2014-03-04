<?php

/**
 * @file
 * Definition of Drupal\views\Tests\Plugin\DisplayFeedTest.
 */

namespace Drupal\views\Tests\Plugin;

/**
 * Tests the feed display plugin.
 *
 * @see Drupal\views\Plugin\views\display\Feed
 */
class DisplayFeedTest extends PluginTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_feed_display');

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('views_ui');

  public static function getInfo() {
    return array(
      'name' => 'Display: Feed plugin',
      'description' => 'Tests the feed display plugin.',
      'group' => 'Views Plugins',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->enableViewsTestModule();

    $admin_user = $this->drupalCreateUser(array('administer views', 'administer site configuration'));
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests feed display admin ui.
   */
  public function testFeedUI() {
    $this->drupalGet('admin/structure/views');

    // Check the attach TO interface.
    $this->drupalGet('admin/structure/views/nojs/display/test_feed_display/feed_1/displays');

    // Load all the options of the checkbox.
    $result = $this->xpath('//div[@id="edit-displays"]/div');
    $options = array();
    foreach ($result as $value) {
      foreach ($value->input->attributes() as $attribute => $value) {
        if ($attribute == 'value') {
          $options[] = (string) $value;
        }
      }
    }

    $this->assertEqual($options, array('default', 'page'), 'Make sure all displays appears as expected.');

    // Post and save this and check the output.
    $this->drupalPost('admin/structure/views/nojs/display/test_feed_display/feed_1/displays', array('displays[page]' => 'page'), t('Apply'));
    $this->drupalGet('admin/structure/views/view/test_feed_display/edit/feed_1');
    $this->assertFieldByXpath('//*[@id="views-feed-1-displays"]', 'Page');

    // Add the default display, so there should now be multiple displays.
    $this->drupalPost('admin/structure/views/nojs/display/test_feed_display/feed_1/displays', array('displays[default]' => 'default'), t('Apply'));
    $this->drupalGet('admin/structure/views/view/test_feed_display/edit/feed_1');
    $this->assertFieldByXpath('//*[@id="views-feed-1-displays"]', 'Multiple displays');
  }

  /**
   * Tests the rendered output.
   */
  public function testFeedOutput() {
    $this->drupalCreateNode();

    // Test the site name setting.
    $site_name = $this->randomName();
    $this->container->get('config.factory')->get('system.site')->set('name', $site_name)->save();

    $this->drupalGet('test-feed-display.xml');
    $result = $this->xpath('//title');
    $this->assertEqual($result[0], $site_name, 'The site title is used for the feed title.');

    $view = $this->container->get('plugin.manager.entity')->getStorageController('view')->load('test_feed_display');
    $display = &$view->getDisplay('feed_1');
    $display['display_options']['sitename_title'] = 0;
    $view->save();

    $this->drupalGet('test-feed-display.xml');
    $result = $this->xpath('//title');
    $this->assertEqual($result[0], 'test_feed_display', 'The display title is used for the feed title.');
  }

}
