<?php

/**
 * @file
 * Contains \Drupal\block\Tests\BlockInterfaceTest.
 */

namespace Drupal\block\Tests;

use Drupal\simpletest\DrupalUnitTestBase;
use Drupal\block\BlockInterface;

/**
 * Test BlockInterface methods to ensure no external dependencies exist.
 */
class BlockInterfaceTest extends DrupalUnitTestBase {
  public static $modules = array('system', 'block', 'block_test', 'user');

  public static function getInfo() {
    return array(
      'name' => 'Block Plugins Tests',
      'description' => 'Tests that the block plugin can work properly without a supporting entity.',
      'group' => 'Block',
    );
  }

  /**
   * Test configuration and subsequent form() and build() method calls.
   *
   * This test is attempting to test the existing block plugin api and all
   * functionality that is expected to remain consistent. The arrays that are
   * used for comparison can change, but only to include elements that are
   * contained within BlockBase or the plugin being tested. Likely these
   * comparison arrays should get smaller, not larger, as more form/build
   * elements are moved into a more suitably responsible class.
   *
   * Instantiation of the plugin is the primary element being tested here. The
   * subsequent method calls are just attempting to cause a failure if a
   * dependency outside of the plugin configuration is required.
   */
  public function testBlockInterface() {
    $manager = $this->container->get('plugin.manager.block');
    $configuration = array(
      'label' => 'Custom Display Message',
    );
    $expected_configuration = array(
      'label' => 'Custom Display Message',
      'display_message' => 'no message set',
      'module' => 'block_test',
      'label_display' => BlockInterface::BLOCK_LABEL_VISIBLE,
      'cache' => DRUPAL_NO_CACHE,
    );
    // Initial configuration of the block at construction time.
    $display_block = $manager->createInstance('test_block_instantiation', $configuration);
    $this->assertIdentical($display_block->getConfiguration(), $expected_configuration, 'The block was configured correctly.');

    // Updating an element of the configuration.
    $display_block->setConfigurationValue('display_message', 'My custom display message.');
    $expected_configuration['display_message'] = 'My custom display message.';
    $this->assertIdentical($display_block->getConfiguration(), $expected_configuration, 'The block configuration was updated correctly.');

    $expected_form = array(
      'module' => array(
        '#type' => 'value',
        '#value' => 'block_test',
      ),
      'label' => array(
        '#type' => 'textfield',
        '#title' => 'Title',
        '#maxlength' => 255,
        '#default_value' => 'Custom Display Message',
        '#required' => TRUE,
      ),
      'label_display' => array(
        '#type' => 'checkbox',
        '#title' => 'Display title',
        '#default_value' => TRUE,
        '#return_value' => 'visible',
      ),
      'display_message' => array(
        '#type' => 'textfield',
        '#title' => t('Display message'),
        '#default_value' => 'My custom display message.',
      ),
    );
    $form_state = array();
    // Ensure there are no form elements that do not belong to the plugin.
    $this->assertIdentical($display_block->buildConfigurationForm(array(), $form_state), $expected_form, 'Only the expected form elements were present.');

    $expected_build = array(
      '#children' => 'My custom display message.',
    );
    // Ensure the build array is proper.
    $this->assertIdentical($display_block->build(), $expected_build, 'The plugin returned the appropriate build array.');

    // Ensure the machine name suggestion is correct. In truth, this is actually
    // testing BlockBase's implementation, not the interface itself.
    $this->assertIdentical($display_block->getMachineNameSuggestion(), 'displaymessage', 'The plugin returned the expected machine name suggestion.');
  }
}
