<?php

/**
 * @file
 * Contains \Drupal\node\Tests\Views\RevisionRelationships.
 */
namespace Drupal\node\Tests\Views;

use Drupal\views\Tests\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests basic node_revision table integration into views.
 */
class RevisionRelationships extends ViewTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node' ,'node_test_views');

  protected function setUp() {
    parent::setUp();

    ViewTestData::importTestViews(get_class($this), array('node_test_views'));
  }

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_node_revision_nid', 'test_node_revision_vid');

  public static function getInfo() {
    return array(
      'name' => 'Node: Revision integration',
      'description' => 'Tests the integration of node_revision table of node module',
      'group' => 'Views module integration',
    );
  }

  /**
   * Create a node with revision and rest result count for both views.
   */
  public function testNodeRevisionRelationship() {
    $node = $this->drupalCreateNode();
    // Create revision of the node.
    $node_revision = clone $node;
    $node_revision->setNewRevision();
    $node_revision->save();
    $column_map = array(
      'vid' => 'vid',
      'node_field_revision_nid' => 'node_field_revision_nid',
      'node_node_field_revision_nid' => 'node_node_field_revision_nid',
    );

    // Here should be two rows.
    $view_nid = views_get_view('test_node_revision_nid');
    $this->executeView($view_nid, array($node->id()));
    $resultset_nid = array(
      array(
        'vid' => '1',
        'node_field_revision_nid' => '1',
        'node_node_field_revision_nid' => '1',
      ),
      array(
        'vid' => '2',
        'node_field_revision_nid' => '1',
        'node_node_field_revision_nid' => '1',
      ),
    );
    $this->assertIdenticalResultset($view_nid, $resultset_nid, $column_map);

    // There should be only one row with active revision 2.
    $view_vid = views_get_view('test_node_revision_vid');
    $this->executeView($view_vid, array($node->id()));
    $resultset_vid = array(
      array(
        'vid' => '2',
        'node_field_revision_nid' => '1',
        'node_node_field_revision_nid' => '1',
      ),
    );
    $this->assertIdenticalResultset($view_vid, $resultset_vid, $column_map);
  }

}
