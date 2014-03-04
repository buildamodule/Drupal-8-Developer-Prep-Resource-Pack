<?php

/**
 * @file
 * Contains \Drupal\views\Tests\Wizard\WizardPluginBaseUnitTest.
 */

namespace Drupal\views\Tests\Wizard;

use Drupal\Core\Language\Language;
use Drupal\views\Tests\ViewUnitTestBase;
use Drupal\views_ui\ViewUI;

/**
 * Tests the wizard code.
 *
 * @see \Drupal\views\Plugin\views\wizard\WizardPluginBase
 */
class WizardPluginBaseUnitTest extends ViewUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('language', 'system');

  /**
   * Contains thw wizard plugin manager.
   *
   * @var \Drupal\views\Plugin\views\wizard\WizardPluginBase
   */
  protected $wizard;

  public static function getInfo() {
    return array(
      'name' => 'Wizard Plugin Base',
      'description' => 'Test the wizard base plugin class',
      'group' => 'Views Wizard',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', 'variable');
    $this->installConfig(array('language'));

    $this->enableModules(array('views_ui'));

    $this->wizard = $this->container->get('plugin.manager.views.wizard')->createInstance('standard:views_test_data', array());
  }

  /**
   * Tests the creating of a view.
   *
   * @see \Drupal\views\Plugin\views\wizard\WizardPluginBase
   */
  public function testCreateView() {
    $form = array();
    $form_state = array();
    $form = $this->wizard->buildForm($form, $form_state);
    $random_id = strtolower($this->randomName());
    $random_label = $this->randomName();
    $random_description = $this->randomName();

    // Add a new language and mark it as default.
    $language = new Language(array(
      'id' => 'it',
      'name' => 'Italian',
      'default' => TRUE,
    ));
    language_save($language);

    $form_state['values'] = array(
      'id' => $random_id,
      'label' => $random_label,
      'description' => $random_description,
      'base_table' => 'views_test_data',
    );

    $this->wizard->validateView($form, $form_state);
    $view = $this->wizard->createView($form, $form_state);
    $this->assertTrue($view instanceof ViewUI, 'The created view is a ViewUI object.');
    $this->assertEqual($view->get('id'), $random_id);
    $this->assertEqual($view->get('label'), $random_label);
    $this->assertEqual($view->get('description'), $random_description);
    $this->assertEqual($view->get('base_table'), 'views_test_data');
    $this->assertEqual($view->get('langcode'), 'it');
  }
}

