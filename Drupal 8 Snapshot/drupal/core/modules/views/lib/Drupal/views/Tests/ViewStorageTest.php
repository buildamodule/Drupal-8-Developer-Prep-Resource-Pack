<?php

/**
 * @file
 * Definition of Drupal\views\Tests\ViewStorageTest.
 */

namespace Drupal\views\Tests;

use Drupal\views\ViewStorageController;
use Drupal\views\Plugin\Core\Entity\View;
use Drupal\views\Plugin\views\display\Page;
use Drupal\views\Plugin\views\display\DefaultDisplay;
use Drupal\views\Plugin\views\display\Feed;

/**
 * Tests the functionality of View and ViewStorageController.
 *
 * @see Drupal\views\Plugin\Core\Entity\View
 * @see Drupal\views\ViewStorageController
 */
class ViewStorageTest extends ViewUnitTestBase {

  /**
   * Properties that should be stored in the configuration.
   *
   * @var array
   */
  protected $config_properties = array(
    'status',
    'module',
    'id',
    'description',
    'tag',
    'base_table',
    'label',
    'core',
    'display',
  );

  /**
   * The configuration entity information from entity_get_info().
   *
   * @var array
   */
  protected $info;

  /**
   * The configuration entity storage controller.
   *
   * @var Drupal\views\ViewStorageController
   */
  protected $controller;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_view_storage');

  public static function getInfo() {
    return array(
      'name' => 'View storage tests',
      'description' => 'Tests the CRUD functionality for a view.',
      'group' => 'Views',
    );
  }

  /**
   * Tests CRUD operations.
   */
  function testConfigurationEntityCRUD() {
    // Get the configuration entity information and controller.
    $this->info = entity_get_info('view');
    $this->controller = $this->container->get('plugin.manager.entity')->getStorageController('view');

    // Confirm that an info array has been returned.
    $this->assertTrue(!empty($this->info) && is_array($this->info), 'The View info array is loaded.');

    // Confirm we have the correct controller class.
    $this->assertTrue($this->controller instanceof ViewStorageController, 'The correct controller is loaded.');

    // CRUD tests.
    $this->loadTests();
    $this->createTests();
    $this->displayTests();

    // Helper method tests
    $this->displayMethodTests();
  }

  /**
   * Tests loading configuration entities.
   */
  protected function loadTests() {
    $view = entity_load('view', 'test_view_storage');
    $data = \Drupal::config('views.view.test_view_storage')->get();

    // Confirm that an actual view object is loaded and that it returns all of
    // expected properties.
    $this->assertTrue($view instanceof View, 'Single View instance loaded.');
    foreach ($this->config_properties as $property) {
      $this->assertTrue($view->get($property) !== NULL, format_string('Property: @property loaded onto View.', array('@property' => $property)));
    }

    // Check the displays have been loaded correctly from config display data.
    $expected_displays = array('default', 'page_1', 'block_1');
    $this->assertEqual(array_keys($view->get('display')), $expected_displays, 'The correct display names are present.');

    // Check each ViewDisplay object and confirm that it has the correct key and
    // property values.
    foreach ($view->get('display') as $key => $display) {
      $this->assertEqual($key, $display['id'], 'The display has the correct ID assigned.');

      // Get original display data and confirm that the display options array
      // exists.
      $original_options = $data['display'][$key];
      foreach ($original_options as $orig_key => $value) {
        $this->assertIdentical($display[$orig_key], $value, format_string('@key is identical to saved data', array('@key' => $key)));
      }
    }

    // Make sure that loaded default views get a UUID.
    $view = views_get_view('test_view_storage');
    $this->assertTrue($view->storage->uuid());
  }

  /**
   * Tests creating configuration entities.
   */
  protected function createTests() {
    // Create a new View instance with empty values.
    $created = $this->controller->create(array());

    $this->assertTrue($created instanceof View, 'Created object is a View.');
    // Check that the View contains all of the properties.
    foreach ($this->config_properties as $property) {
      $this->assertTrue(property_exists($created, $property), format_string('Property: @property created on View.', array('@property' => $property)));
    }

    // Create a new View instance with config values.
    $values = \Drupal::config('views.view.test_view_storage')->get();
    $created = $this->controller->create($values);

    $this->assertTrue($created instanceof View, 'Created object is a View.');
    // Check that the View contains all of the properties.
    $properties = $this->config_properties;
    // Remove display from list.
    array_pop($properties);

    // Test all properties except displays.
    foreach ($properties as $property) {
      $this->assertTrue($created->get($property) !== NULL, format_string('Property: @property created on View.', array('@property' => $property)));
      $this->assertIdentical($values[$property], $created->get($property), format_string('Property value: @property matches configuration value.', array('@property' => $property)));
    }

    // Check the UUID of the loaded View.
    $created->set('id', 'test_view_storage_new');
    $created->save();
    $created_loaded = entity_load('view', 'test_view_storage_new');
    $this->assertIdentical($created->uuid(), $created_loaded->uuid(), 'The created UUID has been saved correctly.');
  }

  /**
   * Tests adding, saving, and loading displays on configuration entities.
   */
  protected function displayTests() {
    // Check whether a display can be added and saved to a View.
    $view = entity_load('view', 'test_view_storage_new');

    $new_id = $view->newDisplay('page', 'Test', 'test');
    $display = $view->get('display');

    // Ensure the right display_plugin is created/instantiated.
    $this->assertEqual($display[$new_id]['display_plugin'], 'page', 'New page display "test" uses the right display plugin.');

    $executable = $view->getExecutable();
    $executable->initDisplay();
    $this->assertTrue($executable->displayHandlers->get($new_id) instanceof Page, 'New page display "test" uses the right display plugin.');

    // To save this with a new ID, we should use createDuplicate().
    $view = $view->createDuplicate();
    $view->set('id', 'test_view_storage_new_new2');
    $view->save();
    $values = \Drupal::config('views.view.test_view_storage_new_new2')->get();

    $this->assertTrue(isset($values['display']['test']) && is_array($values['display']['test']), 'New display was saved.');
  }

  /**
   * Tests the display related functions like getDisplaysList().
   */
  protected function displayMethodTests() {
    // Enable the system module so l() can work using url_alias table.
    $this->enableModules(array('system'));
    $this->installSchema('system', 'url_alias');

    $config['display'] = array(
      'page_1' => array(
        'display_options' => array('path' => 'test'),
        'display_plugin' => 'page',
        'id' => 'page_2',
        'display_title' => 'Page 1',
        'position' => 1
      ),
      'feed_1' => array(
        'display_options' => array('path' => 'test.xml'),
        'display_plugin' => 'feed',
        'id' => 'feed',
        'display_title' => 'Feed',
        'position' => 2
      ),
      'page_2' => array(
        'display_options' => array('path' => 'test/%/extra'),
        'display_plugin' => 'page',
        'id' => 'page_2',
        'display_title' => 'Page 2',
        'position' => 3
      )
    );
    $view = $this->controller->create($config);

    // Tests Drupal\views\Plugin\Core\Entity\View::addDisplay()
    $view = $this->controller->create(array());
    $random_title = $this->randomName();

    $id = $view->addDisplay('page', $random_title);
    $this->assertEqual($id, 'page_1', format_string('Make sure the first display (%id_new) has the expected ID (%id)', array('%id_new' => $id, '%id' => 'page_1')));
    $display = $view->get('display');
    $this->assertEqual($display[$id]['display_title'], $random_title);

    $random_title = $this->randomName();
    $id = $view->addDisplay('page', $random_title);
    $display = $view->get('display');
    $this->assertEqual($id, 'page_2', format_string('Make sure the second display (%id_new) has the expected ID (%id)', array('%id_new' => $id, '%id' => 'page_2')));
    $this->assertEqual($display[$id]['display_title'], $random_title);

    $id = $view->addDisplay('page');
    $display = $view->get('display');
    $this->assertEqual($display[$id]['display_title'], 'Page 3');

    // Tests Drupal\views\Plugin\Core\Entity\View::generateDisplayId().
    // @todo Sadly this method is not public so it cannot be tested.
    // $view = $this->controller->create(array());
    // $this->assertEqual($view->generateDisplayId('default'), 'default', 'The plugin ID for default is always default.');
    // $this->assertEqual($view->generateDisplayId('feed'), 'feed_1', 'The generated ID for the first instance of a plugin type should have an suffix of _1.');
    // $view->addDisplay('feed', 'feed title');
    // $this->assertEqual($view->generateDisplayId('feed'), 'feed_2', 'The generated ID for the first instance of a plugin type should have an suffix of _2.');

    // Tests Drupal\views\Plugin\Core\Entity\View::newDisplay().
    $view = $this->controller->create(array());
    $view->newDisplay('default');

    $display = $view->newDisplay('page');
    $this->assertEqual($display, 'page_1');
    $display = $view->newDisplay('page');
    $this->assertEqual($display, 'page_2');
    $display = $view->newDisplay('feed');
    $this->assertEqual($display, 'feed_1');

    $executable = $view->getExecutable();
    $executable->initDisplay();

    $this->assertTrue($executable->displayHandlers->get('page_1') instanceof Page);
    $this->assertTrue($executable->displayHandlers->get('page_1')->default_display instanceof DefaultDisplay);
    $this->assertTrue($executable->displayHandlers->get('page_2') instanceof Page);
    $this->assertTrue($executable->displayHandlers->get('page_2')->default_display instanceof DefaultDisplay);
    $this->assertTrue($executable->displayHandlers->get('feed_1') instanceof Feed);
    $this->assertTrue($executable->displayHandlers->get('feed_1')->default_display instanceof DefaultDisplay);

    // Tests item related methods().
    $view = $this->controller->create(array('base_table' => 'views_test_data'));
    $view->addDisplay('default');
    $view = $view->getExecutable();

    $display_id = 'default';
    $expected_items = array();
    // Tests addItem with getItem.
    // Therefore add one item without any optioins and one item with some
    // options.
    $id1 = $view->addItem($display_id, 'field', 'views_test_data', 'id');
    $item1 = $view->getItem($display_id, 'field', 'id');
    $expected_items[$id1] = $expected_item = array(
      'id' => 'id',
      'table' => 'views_test_data',
      'field' => 'id',
      'plugin_id' => 'numeric',
      'provider' => 'views',
    );
    $this->assertEqual($item1, $expected_item);

    $options = array(
      'alter' => array(
        'text' => $this->randomName()
      )
    );
    $id2 = $view->addItem($display_id, 'field', 'views_test_data', 'name', $options);
    $item2 = $view->getItem($display_id, 'field', 'name');
    $expected_items[$id2] = $expected_item = array(
      'id' => 'name',
      'table' => 'views_test_data',
      'field' => 'name',
      'plugin_id' => 'standard',
      'provider' => 'views',
    ) + $options;
    $this->assertEqual($item2, $expected_item);

    // Tests the expected fields from the previous additions.
    $this->assertEqual($view->getItems('field', $display_id), $expected_items);

    // Alter an existing item via setItem and check the result via getItem
    // and getItems.
    $item = array(
      'alter' => array(
        'text' => $this->randomName(),
      )
    ) + $item1;
    $expected_items[$id1] = $item;
    $view->setItem($display_id, 'field', $id1, $item);
    $this->assertEqual($view->getItem($display_id, 'field', 'id'), $item);
    $this->assertEqual($view->getItems('field', $display_id), $expected_items);

    // Test removeItem method.
    unset($expected_items[$id2]);
    $view->removeItem($display_id, 'field', $id2);
    $this->assertEqual($view->getItems('field', $display_id), $expected_items);
  }

  /**
   * Tests the createDuplicate() View method.
   */
  public function testCreateDuplicate() {
    $view = views_get_view('test_view_storage');
    $copy = $view->storage->createDuplicate();

    $this->assertTrue($copy instanceof View, 'The copied object is a View.');

    // Check that the original view and the copy have different UUIDs.
    $this->assertNotIdentical($view->storage->uuid(), $copy->uuid(), 'The copied view has a new UUID.');

    // Check the 'name' (ID) is using the View objects default value (NULL) as it
    // gets unset.
    $this->assertIdentical($copy->id(), NULL, 'The ID has been reset.');

    // Check the other properties.
    // @todo Create a reusable property on the base test class for these?
    $config_properties = array(
      'disabled',
      'description',
      'tag',
      'base_table',
      'label',
      'core',
    );

    foreach ($config_properties as $property) {
      $this->assertIdentical($view->storage->get($property), $copy->get($property), format_string('@property property is identical.', array('@property' => $property)));
    }

    // Check the displays are the same.
    $copy_display = $copy->get('display');
    foreach ($view->storage->get('display') as $id => $display) {
      // assertIdentical will not work here.
      $this->assertEqual($display, $copy_display[$id], format_string('The @display display has been copied correctly.', array('@display' => $id)));
    }
  }

}
