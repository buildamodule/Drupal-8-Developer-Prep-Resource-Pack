<?php

/**
 * @file
 * Contains \Drupal\views\Tests\Plugin\ExposedFormTest.
 */

namespace Drupal\views\Tests\Plugin;

use Drupal\views\Tests\ViewTestBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Tests exposed forms.
 */
class ExposedFormTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_reset_button', 'test_exposed_block');

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('views_ui', 'block');

  public static function getInfo() {
    return array(
      'name' => 'Exposed forms',
      'description' => 'Test exposed forms functionality.',
      'group' => 'Views Plugins',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'article'));

    // Create some random nodes.
    for ($i = 0; $i < 5; $i++) {
      $this->drupalCreateNode();
    }
  }

  /**
   * Tests whether the reset button works on an exposed form.
   */
  public function testResetButton() {
    // Test the button is hidden when there is no exposed input.
    $this->drupalGet('test_reset_button');
    $this->assertNoField('edit-reset');

    $this->drupalGet('test_reset_button', array('query' => array('type' => 'article')));
    // Test that the type has been set.
    $this->assertFieldById('edit-type', 'article', 'Article type filter set.');

    // Test the reset works.
    $this->drupalGet('test_reset_button', array('query' => array('op' => 'Reset')));
    $this->assertResponse(200);
    // Test the type has been reset.
    $this->assertFieldById('edit-type', 'All', 'Article type filter has been reset.');

    // Test the button is hidden after reset.
    $this->assertNoField('edit-reset');
  }

  /**
   * Tests, whether and how the reset button can be renamed.
   */
  public function testRenameResetButton() {
    // Rename the label of the reset button.
    $view = views_get_view('test_reset_button');
    $view->setDisplay();

    $exposed_form = $view->display_handler->getOption('exposed_form');
    $exposed_form['options']['reset_button_label'] = $expected_label = $this->randomName();
    $exposed_form['options']['reset_button'] = TRUE;
    $view->display_handler->setOption('exposed_form', $exposed_form);
    $view->save();

    views_invalidate_cache();

    // Look whether the reset button label changed.
    $this->drupalGet('test_reset_button', array('query' => array('type' => 'article')));
    $this->assertResponse(200);

    $this->helperButtonHasLabel('edit-reset', $expected_label);
  }

  /**
   * Tests the exposed form markup.
   */
  public function testExposedFormRender() {
    $view = views_get_view('test_reset_button');
    $this->executeView($view);
    $exposed_form = $view->display_handler->getPlugin('exposed_form');
    $output = $exposed_form->renderExposedForm();
    $this->drupalSetContent(drupal_render($output));

    $this->assertFieldByXpath('//form/@id', $this->getExpectedExposedFormId($view), 'Expected form ID found.');

    $expected_action = url($view->display_handler->getUrl());
    $this->assertFieldByXPath('//form/@action', $expected_action, 'The expected value for the action attribute was found.');
  }

  /**
   * Tests the exposed block functionality.
   */
  public function testExposedBlock() {
    $view = Views::getView('test_exposed_block');
    $view->setDisplay('page_1');
    $block = $this->drupalPlaceBlock('views_exposed_filter_block:test_exposed_block-page_1');
    $this->drupalGet('test_exposed_block');

    // Test there is an exposed form in a block.
    $xpath = $this->buildXPathQuery('//div[@id=:id]/div/form/@id', array(':id' => 'block-' . $block->get('machine_name')));
    $this->assertFieldByXpath($xpath, $this->getExpectedExposedFormId($view), 'Expected form found in views block.');

    // Test there is not an exposed form in the view page content area.
    $xpath = $this->buildXPathQuery('//div[@class="view-content"]/form/@id', array(':id' => 'block-' . $block->get('machine_name')));
    $this->assertNoFieldByXpath($xpath, $this->getExpectedExposedFormId($view), 'No exposed form found in views content region.');

    // Test there is only one views exposed form on the page.
    $elements = $this->xpath('//form[@id=:id]', array(':id' => $this->getExpectedExposedFormId($view)));
    $this->assertEqual(count($elements), 1, 'One exposed form block found.');
  }

  /**
   * Returns a views exposed form ID.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to create an ID for.
   *
   * @return string
   *   The form ID.
   */
  protected function getExpectedExposedFormId(ViewExecutable $view) {
    return drupal_clean_css_identifier('views-exposed-form-' . $view->storage->id() . '-' . $view->current_display);
  }

}
