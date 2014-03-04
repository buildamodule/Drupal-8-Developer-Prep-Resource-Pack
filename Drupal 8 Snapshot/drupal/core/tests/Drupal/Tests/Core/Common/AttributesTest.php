<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Common\AttributesTest.
 */

namespace Drupal\Tests\Core\Common;

use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Drupal\Core\Template\Attribute functionality.
 */
class AttributesTest extends UnitTestCase {

  public static function getInfo() {
    return array(
      'name' => 'HTML Attributes',
      'description' => 'Tests the Drupal\Core\Template\Attribute functionality.',
      'group' => 'Common',
    );
  }

  /**
   * Provides data for the Attribute test.
   *
   * @return array
   */
  public function providerTestAttributeData() {
    return array(
      // Verify that special characters are HTML encoded.
      array(array('title' => '&"\'<>'), ' title="&amp;&quot;&#039;&lt;&gt;"', 'HTML encode attribute values.'),
      // Verify multi-value attributes are concatenated with spaces.
      array(array('class' => array('first', 'last')), ' class="first last"', 'Concatenate multi-value attributes.'),
      // Verify empty attribute values are rendered.
      array(array('alt' => ''), ' alt=""', 'Empty attribute value #1.'),
      array(array('alt' => NULL), ' alt=""', 'Empty attribute value #2.'),
      // Verify multiple attributes are rendered.
      array(
        array(
          'id' => 'id-test',
          'class' => array('first', 'last'),
          'alt' => 'Alternate',
        ),
        ' id="id-test" class="first last" alt="Alternate"',
        'Multiple attributes.'
      ),
      // Verify empty attributes array is rendered.
      array(array(), '', 'Empty attributes array.'),
    );
  }

  /**
   * Tests casting an Attribute object to a string.
   *
   * @see \Drupal\Core\Template\Attribute::__toString()
   *
   * @dataProvider providerTestAttributeData
   */
  function testDrupalAttributes($attributes, $expected, $message) {
    $this->assertSame($expected, (string) new Attribute($attributes), $message);
  }

  /**
   * Test attribute iteration
   */
  public function testAttributeIteration() {
    $attribute = new Attribute(array('key1' => 'value1'));
    foreach ($attribute as $value) {
      $this->assertSame((string) $value, 'value1', 'Iterate over attribute.');
    }
  }

}
