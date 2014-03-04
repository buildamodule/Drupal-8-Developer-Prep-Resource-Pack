<?php

/**
 * @file
 * Definition of Drupal\node\Tests\SummaryLengthTest.
 */

namespace Drupal\node\Tests;

/**
 * Tests the summary length functionality.
 */
class SummaryLengthTest extends NodeTestBase {
  public static function getInfo() {
    return array(
      'name' => 'Summary length',
      'description' => 'Test summary length.',
      'group' => 'Node',
    );
  }

  /**
   * Tests the node summary length functionality.
   */
  function testSummaryLength() {
    // Create a node to view.
    $settings = array(
      'body' => array(array('value' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam vitae arcu at leo cursus laoreet. Curabitur dui tortor, adipiscing malesuada tempor in, bibendum ac diam. Cras non tellus a libero pellentesque condimentum. What is a Drupalism? Suspendisse ac lacus libero. Ut non est vel nisl faucibus interdum nec sed leo. Pellentesque sem risus, vulputate eu semper eget, auctor in libero. Ut fermentum est vitae metus convallis scelerisque. Phasellus pellentesque rhoncus tellus, eu dignissim purus posuere id. Quisque eu fringilla ligula. Morbi ullamcorper, lorem et mattis egestas, tortor neque pretium velit, eget eleifend odio turpis eu purus. Donec vitae metus quis leo pretium tincidunt a pulvinar sem. Morbi adipiscing laoreet mauris vel placerat. Nullam elementum, nisl sit amet scelerisque malesuada, dolor nunc hendrerit quam, eu ultrices erat est in orci. Curabitur feugiat egestas nisl sed accumsan.')),
      'promote' => 1,
    );
    $node = $this->drupalCreateNode($settings);
    $this->assertTrue(node_load($node->id()), 'Node created.');

    // Create user with permission to view the node.
    $web_user = $this->drupalCreateUser(array('access content', 'administer content types'));
    $this->loggedInUser = $web_user;

    $controller = $this->container->get('plugin.manager.entity')->getRenderController('node');
    // Render the node as a teaser.
    $content = $controller->view($node, 'teaser');
    $this->assertTrue(strlen($content['body'][0]['#markup']) < 600, 'Teaser is less than 600 characters long.');
    $this->drupalSetContent(drupal_render($content));
    // The string 'What is a Drupalism?' is between the 200th and 600th
    // characters of the node body, so it should be included if the summary is
    // 600 characters long.
    $expected = 'What is a Drupalism?';
    $this->assertRaw($expected);

    // Change the teaser length for "Basic page" content type.
    $display = entity_get_display('node', $node->type, 'teaser');
    $display_options = $display->getComponent('body');
    $display_options['settings']['trim_length'] = 200;
    $display->setComponent('body', $display_options)
      ->save();

    // Render the node as a teaser again and check that the summary is now only
    // 200 characters in length and so does not include 'What is a Drupalism?'.
    $content = $controller->view($node, 'teaser');
    $this->assertTrue(strlen($content['body'][0]['#markup']) < 200, 'Teaser is less than 200 characters long.');
    $this->drupalSetContent(drupal_render($content));
    $this->assertText($node->label());
    $this->assertNoRaw($expected);
  }

}
