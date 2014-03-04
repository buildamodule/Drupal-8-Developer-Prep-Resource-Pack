<?php

/**
 * @file
 * Definition of Drupal\views\Tests\Wizard\SortingTest.
 */

namespace Drupal\views\Tests\Wizard;

/**
 * Tests the ability of the views wizard to create views with sorts.
 */
class SortingTest extends WizardTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Sorting functionality',
      'description' => 'Test the ability of the views wizard to create views with sorts.',
      'group' => 'Views Wizard',
    );
  }

  /**
   * Tests the sorting functionality.
   */
  function testSorting() {
    // Create nodes, each with a different creation time so that we can do a
    // meaningful sort.
    $node1 = $this->drupalCreateNode(array('created' => REQUEST_TIME));
    $node2 = $this->drupalCreateNode(array('created' => REQUEST_TIME + 1));
    $node3 = $this->drupalCreateNode(array('created' => REQUEST_TIME + 2));

    // Create a view that sorts oldest first.
    $view1 = array();
    $view1['label'] = $this->randomName(16);
    $view1['id'] = strtolower($this->randomName(16));
    $view1['description'] = $this->randomName(16);
    $view1['show[sort]'] = 'node_field_data-created:ASC';
    $view1['page[create]'] = 1;
    $view1['page[title]'] = $this->randomName(16);
    $view1['page[path]'] = $this->randomName(16);
    $this->drupalPost('admin/structure/views/add', $view1, t('Save and edit'));
    $this->drupalGet($view1['page[path]']);
    $this->assertResponse(200);

    // Make sure the view shows the nodes in the expected order.
    $this->assertUrl($view1['page[path]']);
    $this->assertText($view1['page[title]']);
    $content = $this->drupalGetContent();
    $this->assertText($node1->label());
    $this->assertText($node2->label());
    $this->assertText($node3->label());
    $pos1 = strpos($content, $node1->label());
    $pos2 = strpos($content, $node2->label());
    $pos3 = strpos($content, $node3->label());
    $this->assertTrue($pos1 < $pos2 && $pos2 < $pos3, 'The nodes appear in the expected order in a view that sorts by oldest first.');

    // Create a view that sorts newest first.
    $view2 = array();
    $view2['label'] = $this->randomName(16);
    $view2['id'] = strtolower($this->randomName(16));
    $view2['description'] = $this->randomName(16);
    $view2['show[sort]'] = 'node_field_data-created:DESC';
    $view2['page[create]'] = 1;
    $view2['page[title]'] = $this->randomName(16);
    $view2['page[path]'] = $this->randomName(16);
    $this->drupalPost('admin/structure/views/add', $view2, t('Save and edit'));
    $this->drupalGet($view2['page[path]']);
    $this->assertResponse(200);

    // Make sure the view shows the nodes in the expected order.
    $this->assertUrl($view2['page[path]']);
    $this->assertText($view2['page[title]']);
    $content = $this->drupalGetContent();
    $this->assertText($node3->label());
    $this->assertText($node2->label());
    $this->assertText($node1->label());
    $pos3 = strpos($content, $node3->label());
    $pos2 = strpos($content, $node2->label());
    $pos1 = strpos($content, $node1->label());
    $this->assertTrue($pos3 < $pos2 && $pos2 < $pos1, 'The nodes appear in the expected order in a view that sorts by newest first.');
  }

}
