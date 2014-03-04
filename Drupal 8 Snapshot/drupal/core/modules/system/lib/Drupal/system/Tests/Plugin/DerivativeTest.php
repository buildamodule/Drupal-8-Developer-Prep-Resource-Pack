<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Plugin\DerivativeTest.
 */

namespace Drupal\system\Tests\Plugin;

/**
 * Tests that derivative plugins are correctly discovered.
 */
class DerivativeTest extends PluginTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Derivative Discovery',
      'description' => 'Tests that derivative plugins are correctly discovered.',
      'group' => 'Plugin API',
    );
  }

  /**
   * Tests getDefinitions() and getDefinition() with a derivativeDecorator.
   */
  function testDerivativeDecorator() {
    // Ensure that getDefinitions() returns the expected definitions.
    $this->assertIdentical($this->mockBlockManager->getDefinitions(), $this->mockBlockExpectedDefinitions);

    // Ensure that getDefinition() returns the expected definition.
    foreach ($this->mockBlockExpectedDefinitions as $id => $definition) {
      $this->assertIdentical($this->mockBlockManager->getDefinition($id), $definition);
    }

    // Ensure that NULL is returned as the definition of a non-existing base
    // plugin, a non-existing derivative plugin, or a base plugin that may not
    // be used without deriving.
    $this->assertIdentical($this->mockBlockManager->getDefinition('non_existing'), NULL, 'NULL returned as the definition of a non-existing base plugin.');
    $this->assertIdentical($this->mockBlockManager->getDefinition('menu:non_existing'), NULL, 'NULL returned as the definition of a non-existing derivative plugin.');
    $this->assertIdentical($this->mockBlockManager->getDefinition('menu'), NULL, 'NULL returned as the definition of a base plugin that may not be used without deriving.');
  }
}
