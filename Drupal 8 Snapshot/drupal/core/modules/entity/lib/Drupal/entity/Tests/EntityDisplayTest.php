<?php

/**
 * @file
 * Contains \Drupal\entity\Tests\EntityDisplayTest.
 */

namespace Drupal\entity\Tests;

use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests the EntityDisplay configuration entities.
 */
class EntityDisplayTest extends DrupalUnitTestBase {

  public static $modules = array('entity', 'field', 'entity_test');

  public static function getInfo() {
    return array(
      'name' => 'Entity display configuration entities',
      'description' => 'Tests the entity display configuration entities.',
      'group' => 'Entity API',
    );
  }

  protected function setUp() {
    parent::setUp();
    $this->installConfig(array('field'));
  }

  /**
   * Tests basic CRUD operations on EntityDisplay objects.
   */
  public function testEntityDisplayCRUD() {
    $display = entity_create('entity_display', array(
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ));

    $expected = array();

    // Check that providing no 'weight' results in the highest current weight
    // being assigned.
    $expected['component_1'] = array('weight' => 0);
    $expected['component_2'] = array('weight' => 1);
    $display->setComponent('component_1');
    $display->setComponent('component_2');
    $this->assertEqual($display->getComponent('component_1'), $expected['component_1']);
    $this->assertEqual($display->getComponent('component_2'), $expected['component_2']);

    // Check that arbitrary options are correctly stored.
    $expected['component_3'] = array('weight' => 10, 'foo' => 'bar');
    $display->setComponent('component_3', $expected['component_3']);
    $this->assertEqual($display->getComponent('component_3'), $expected['component_3']);

    // Check that the display can be properly saved and read back.
    $display->save();
    $display = entity_load('entity_display', $display->id());
    foreach (array('component_1', 'component_2', 'component_3') as $name) {
      $this->assertEqual($display->getComponent($name), $expected[$name]);
    }

    // Check that getComponents() returns options for all components.
    $this->assertEqual($display->getComponents(), $expected);

    // Check that a component can be removed.
    $display->removeComponent('component_3');
    $this->assertNULL($display->getComponent('component_3'));

    // Check that the removal is correctly persisted.
    $display->save();
    $display = entity_load('entity_display', $display->id());
    $this->assertNULL($display->getComponent('component_3'));

    // Check that CreateCopy() creates a new component that can be correclty
    // saved.
    $new_display = $display->createCopy('other_view_mode');
    $new_display->save();
    $new_display = entity_load('entity_display', $new_display->id());
    $this->assertEqual($new_display->targetEntityType, $display->targetEntityType);
    $this->assertEqual($new_display->bundle, $display->bundle);
    $this->assertEqual($new_display->mode, 'other_view_mode');
    $this->assertEqual($new_display->getComponents(), $display->getComponents());
  }

  /**
   * Tests entity_get_display().
   */
  public function testEntityGetDisplay() {
    // Check that entity_get_display() returns a fresh object when no
    // configuration entry exists.
    $display = entity_get_display('entity_test', 'entity_test', 'default');
    $this->assertTrue($display->isNew());

    // Add some components and save the display.
    $display->setComponent('component_1', array('weight' => 10))
      ->save();

    // Check that entity_get_display() returns the correct object.
    $display = entity_get_display('entity_test', 'entity_test', 'default');
    $this->assertFalse($display->isNew());
    $this->assertEqual($display->id, 'entity_test.entity_test.default');
    $this->assertEqual($display->getComponent('component_1'), array('weight' => 10));
  }

  /**
   * Tests the behavior of a field component within an EntityDisplay object.
   */
  public function testExtraFieldComponent() {
    $display = entity_create('entity_display', array(
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ));

    // Check that the default visibility taken into account for extra fields
    // unknown in the display.
    $this->assertEqual($display->getComponent('display_extra_field'), array('weight' => 5));
    $this->assertNull($display->getComponent('display_extra_field_hidden'));

    // Check that setting explicit options overrides the defaults.
    $display->removeComponent('display_extra_field');
    $display->setComponent('display_extra_field_hidden', array('weight' => 10));
    $this->assertNull($display->getComponent('display_extra_field'));
    $this->assertEqual($display->getComponent('display_extra_field_hidden'), array('weight' => 10));
  }

  /**
   * Tests the behavior of a field component within an EntityDisplay object.
   */
  public function testFieldComponent() {
    $this->enableModules(array('field_sql_storage', 'field_test'));

    $display = entity_create('entity_display', array(
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ));

    $field_name = 'test_field';
    // Create a field and an instance.
    $field = entity_create('field_entity', array(
      'field_name' => $field_name,
      'type' => 'test_field'
    ));
    $field->save();
    $instance = entity_create('field_instance', array(
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ));
    $instance->save();

    // Check that providing no options results in default values being used.
    $display->setComponent($field_name);
    $field_type_info = \Drupal::service('plugin.manager.entity.field.field_type')->getDefinition($field->type);
    $default_formatter = $field_type_info['default_formatter'];
    $formatter_settings =  \Drupal::service('plugin.manager.field.formatter')->getDefinition($default_formatter);
    $expected = array(
      'weight' => 0,
      'label' => 'above',
      'type' => $default_formatter,
      'settings' => $formatter_settings['settings'],
    );
    $this->assertEqual($display->getComponent($field_name), $expected);

    // Check that the getFormatter() method returns the correct formatter plugin.
    $formatter = $display->getRenderer($field_name);
    $this->assertEqual($formatter->getPluginId(), $default_formatter);
    $this->assertEqual($formatter->getSettings(), $formatter_settings['settings']);

    // Check that the formatter is statically persisted, by assigning an
    // arbitrary property and reading it back.
    $random_value = $this->randomString();
    $formatter->randomValue = $random_value;
    $formatter = $display->getRenderer($field_name);
    $this->assertEqual($formatter->randomValue, $random_value);

    // Check that changing the definition creates a new formatter.
    $display->setComponent($field_name, array(
      'type' => 'field_test_multiple',
    ));
    $formatter = $display->getRenderer($field_name);
    $this->assertEqual($formatter->getPluginId(), 'field_test_multiple');
    $this->assertFalse(isset($formatter->randomValue));

    // Check that specifying an unknown formatter (e.g. case of a disabled
    // module) gets stored as is in the display, but results in the default
    // formatter being used.
    $display->setComponent($field_name, array(
      'type' => 'unknown_formatter',
    ));
    $options = $display->getComponent($field_name);
    $this->assertEqual($options['type'], 'unknown_formatter');
    $formatter = $display->getRenderer($field_name);
    $this->assertEqual($formatter->getPluginId(), $default_formatter);
  }

  /**
   * Tests renaming and deleting a bundle.
   */
  public function testRenameDeleteBundle() {
    $this->enableModules(array('field_sql_storage', 'field_test', 'node', 'system', 'text'));
    $this->installSchema('system', array('variable'));
    $this->installSchema('node', array('node'));

    // Create a node bundle, display and form display object.
    entity_create('node_type', array('type' => 'article'))->save();
    entity_get_display('node', 'article', 'default')->save();
    entity_get_form_display('node', 'article', 'default')->save();

    // Rename the article bundle and assert the entity display is renamed.
    $info = node_type_load('article');
    $info->old_type = 'article';
    $info->type = 'article_rename';
    $info->save();
    $old_display = entity_load('entity_display', 'node.article.default');
    $this->assertFalse($old_display);
    $old_form_display = entity_load('entity_form_display', 'node.article.default');
    $this->assertFalse($old_form_display);
    $new_display = entity_load('entity_display', 'node.article_rename.default');
    $this->assertEqual('article_rename', $new_display->bundle);
    $this->assertEqual('node.article_rename.default', $new_display->id);
    $new_form_display = entity_load('entity_form_display', 'node.article_rename.default');
    $this->assertEqual('article_rename', $new_form_display->bundle);
    $this->assertEqual('node.article_rename.default', $new_form_display->id);

    // Delete the bundle.
    $info->delete();
    $display = entity_load('entity_display', 'node.article_rename.default');
    $this->assertFalse($display);
    $form_display = entity_load('entity_form_display', 'node.article_rename.default');
    $this->assertFalse($form_display);
  }

  /**
   * Tests deleting field instance.
   */
  public function testDeleteFieldInstance() {
    $this->enableModules(array('field_sql_storage', 'field_test'));

    $field_name = 'test_field';
    // Create a field and an instance.
    $field = entity_create('field_entity', array(
      'field_name' => $field_name,
      'type' => 'test_field'
    ));
    $field->save();
    $instance = entity_create('field_instance', array(
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ));
    $instance->save();

    // Create an entity display.
    entity_create('entity_display', array(
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'viewMode' => 'default',
    ))->setComponent($field_name)->save();

    // Delete the instance.
    $instance->delete();

    // Check that the component has been removed from the entity display.
    $display = entity_get_display('entity_test', 'entity_test', 'default');
    $this->assertFalse($display->getComponent($field_name));
  }

}
