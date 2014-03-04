<?php

/**
 * @file
 * Contains \Drupal\node\Tests\Views\RowPluginTest.
 */

namespace Drupal\node\Tests\Views;
use Drupal\Core\Language\Language;

/**
 * Tests the node row plugin.
 *
 * @see \Drupal\node\Plugin\views\row\NodeRow
 */
class RowPluginTest extends NodeTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'comment');

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_node_row_plugin');

  /**
   * Contains all comments keyed by node used by the test.
   *
   * @var array
   */
  protected $comments;

  /**
   * Contains all nodes used by this test.
   *
   * @var array
   */
  protected $nodes;

  public static function getInfo() {
    return array(
      'name' => 'Node: Row plugin',
      'description' => 'Tests the node row plugin.',
      'group' => 'Views module integration',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'article'));

    // Create two nodes, with 5 comments on all of them.
    for ($i = 0; $i < 2; $i++) {
      $this->nodes[] = $this->drupalCreateNode(
        array(
          'type' => 'article',
          'body' => array(
            array(
              'value' => $this->randomName(42),
              'format' => filter_default_format(),
              'summary' => $this->randomName(),
            ),
          ),
        )
      );
    }

    foreach ($this->nodes as $node) {
      for ($i = 0; $i < 5; $i++) {
        $this->comments[$node->id()][] = $this->drupalCreateComment(array('nid' => $node->id()));
      }
    }
  }

  /**
   * Helper function to create a random comment.
   *
   * @param array $settings
   *   (optional) An associative array of settings for the comment, as used in
   *   entity_create().
   *
   * @return \Drupal\comment\Plugin\Core\Entity\Comment
   *   Returns the created and saved comment.
   */
  public function drupalCreateComment(array $settings = array()) {
    $node = node_load($settings['nid']);
    $settings += array(
      'subject' => $this->randomName(),
      'node_type' => "comment_node_{$node->bundle()}",
      'comment_body' => $this->randomName(40),
    );

    $comment = entity_create('comment', $settings);
    $comment->save();
    return $comment;
  }

  /**
   * Tests the node row plugin.
   */
  public function testRowPlugin() {
    $view = views_get_view('test_node_row_plugin');
    $view->initDisplay();
    $view->setDisplay('page_1');
    $view->initStyle();
    $view->rowPlugin->options['view_mode'] = 'full';

    // Test with view_mode full.
    $output = $view->preview();
    $output = drupal_render($output);
    foreach ($this->nodes as $node) {
      $body = $node->body;
      $teaser = $body[Language::LANGCODE_NOT_SPECIFIED][0]['summary'];
      $full = $body[Language::LANGCODE_NOT_SPECIFIED][0]['value'];
      $this->assertFalse(strpos($output, $teaser) !== FALSE, 'Make sure the teaser appears in the output of the view.');
      $this->assertTrue(strpos($output, $full) !== FALSE, 'Make sure the full text appears in the output of the view.');
    }

    // Test with teasers.
    $view->rowPlugin->options['view_mode'] = 'teaser';
    $output = $view->preview();
    $output = drupal_render($output);
    foreach ($this->nodes as $node) {
      $body = $node->body;
      $teaser = $body[Language::LANGCODE_NOT_SPECIFIED][0]['summary'];
      $full = $body[Language::LANGCODE_NOT_SPECIFIED][0]['value'];
      $this->assertTrue(strpos($output, $teaser) !== FALSE, 'Make sure the teaser appears in the output of the view.');
      $this->assertFalse(strpos($output, $full) !== FALSE, 'Make sure the full text does not appears in the output of the view if teaser is set as viewmode.');
    }

    // Test with links disabled.
    $view->rowPlugin->options['links'] = FALSE;
    $output = $view->preview();
    $output = drupal_render($output);
    $this->drupalSetContent($output);
    foreach ($this->nodes as $node) {
      $this->assertFalse($this->xpath('//li[contains(@class, :class)]/a[contains(@href, :href)]', array(':class' => 'node-readmore', ':href' => "node/{$node->id()}")), 'Make sure no readmore link appears.');
    }

    // Test with links enabled.
    $view->rowPlugin->options['links'] = TRUE;
    $output = $view->preview();
    $output = drupal_render($output);
    $this->drupalSetContent($output);
    foreach ($this->nodes as $node) {
      $this->assertTrue($this->xpath('//li[contains(@class, :class)]/a[contains(@href, :href)]', array(':class' => 'node-readmore', ':href' => "node/{$node->id()}")), 'Make sure no readmore link appears.');
    }

    // Test with comments enabled.
    $view->rowPlugin->options['comments'] = TRUE;
    $output = $view->preview();
    $output = drupal_render($output);
    foreach ($this->nodes as $node) {
      foreach ($this->comments[$node->id()] as $comment) {
        $this->assertTrue(strpos($output, $comment->comment_body->value) !== FALSE, 'Make sure the comment appears in the output.');
      }
    }

    // Test with comments disabled.
    $view->rowPlugin->options['comments'] = FALSE;
    $output = $view->preview();
    $output = drupal_render($output);
    foreach ($this->nodes as $node) {
      foreach ($this->comments[$node->id()] as $comment) {
        $this->assertFalse(strpos($output, $comment->comment_body->value) !== FALSE, 'Make sure the comment does not appears in the output when the comments option disabled.');
      }
    }
  }

}
