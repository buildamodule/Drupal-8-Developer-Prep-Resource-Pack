<?php

/**
 * @file
 * Contains \Drupal\views\Tests\Plugin\DisplayPageWebTest.
 */

namespace Drupal\views\Tests\Plugin;

/**
 * Tests the views page display plugin as webtest.
 */
class DisplayPageWebTest extends PluginTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_page_display_arguments', 'test_page_display_menu');

  public static function getInfo() {
    return array(
      'name' => 'Display: Page plugin (web)',
      'description' => 'Tests the page display plugin (web).',
      'group' => 'Views Plugins',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->enableViewsTestModule();
  }

  /**
   * Tests arguments.
   */
  public function testArguments() {
    $this->drupalGet('test_route_without_arguments');
    $this->assertResponse(200);
    $result = $this->xpath('//span[@class="field-content"]');
    $this->assertEqual(count($result), 5, 'All entries was returned');

    $this->drupalGet('test_route_without_arguments/1');
    $this->assertResponse(404);

    $this->drupalGet('test_route_with_argument/1');
    $this->assertResponse(200);
    $result = $this->xpath('//span[@class="field-content"]');
    $this->assertEqual(count($result), 1, 'Ensure that just the filtered entry was returned.');
    $this->assertEqual((string) $result[0], 1, 'The passed ID was returned.');

    $this->drupalGet('test_route_with_suffix/1/suffix');
    $this->assertResponse(200);
    $result = $this->xpath('//span[@class="field-content"]');
    $this->assertEqual(count($result), 1, 'Ensure that just the filtered entry was returned.');
    $this->assertEqual((string) $result[0], 1, 'The passed ID was returned.');

    $this->drupalGet('test_route_with_suffix_and_argument/1/suffix/2');
    $this->assertResponse(200);
    $result = $this->xpath('//span[@class="field-content"]');
    $this->assertEqual(count($result), 0, 'No result was returned.');

    $this->drupalGet('test_route_with_suffix_and_argument/1/suffix/1');
    $this->assertResponse(200);
    $result = $this->xpath('//span[@class="field-content"]');
    $this->assertEqual(count($result), 1, 'Ensure that just the filtered entry was returned.');
    $this->assertEqual((string) $result[0], 1, 'The passed ID was returned.');
  }

  /**
   * Tests menu settings of page displays.
   */
  public function testPageDisplayMenu() {
    $this->drupalGet('test_page_display_menu');
    $this->assertResponse(200);
    $element = $this->xpath('//ul[contains(@class, :ul_class)]//a[contains(@class, :a_class)]', array(
      ':ul_class' => 'tabs primary',
      ':a_class' => 'active',
    ));
    $this->assertEqual((string) $element[0], t('Test default tab'));
    $this->assertTitle(t('Test default page | Drupal'));

    $this->drupalGet('test_page_display_menu/default');
    $this->assertResponse(404);

    $this->drupalGet('test_page_display_menu/local');
    $this->assertResponse(200);
    $element = $this->xpath('//ul[contains(@class, :ul_class)]//a[contains(@class, :a_class)]', array(
      ':ul_class' => 'tabs primary',
      ':a_class' => 'active',
    ));
    $this->assertEqual((string) $element[0], t('Test local tab'));
    $this->assertTitle(t('Test local page | Drupal'));
  }

}
