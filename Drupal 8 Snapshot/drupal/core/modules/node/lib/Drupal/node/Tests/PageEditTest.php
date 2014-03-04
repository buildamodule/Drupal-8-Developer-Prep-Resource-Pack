<?php

/**
 * @file
 * Definition of Drupal\node\Tests\PageEditTest.
 */

namespace Drupal\node\Tests;

use Drupal\Core\Language\Language;

/**
 * Tests the node edit functionality.
 */
class PageEditTest extends NodeTestBase {
  protected $web_user;
  protected $admin_user;

  public static function getInfo() {
    return array(
      'name' => 'Node edit',
      'description' => 'Create a node and test node edit functionality.',
      'group' => 'Node',
    );
  }

  function setUp() {
    parent::setUp();

    $this->web_user = $this->drupalCreateUser(array('edit own page content', 'create page content'));
    $this->admin_user = $this->drupalCreateUser(array('bypass node access', 'administer nodes'));
  }

  /**
   * Checks node edit functionality.
   */
  function testPageEdit() {
    $this->drupalLogin($this->web_user);

    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $title_key = "title";
    $body_key = "body[$langcode][0][value]";
    // Create node to edit.
    $edit = array();
    $edit[$title_key] = $this->randomName(8);
    $edit[$body_key] = $this->randomName(16);
    $this->drupalPost('node/add/page', $edit, t('Save'));

    // Check that the node exists in the database.
    $node = $this->drupalGetNodeByTitle($edit[$title_key]);
    $this->assertTrue($node, 'Node found in database.');

    // Check that "edit" link points to correct page.
    $this->clickLink(t('Edit'));
    $edit_url = url("node/$node->nid/edit", array('absolute' => TRUE));
    $actual_url = $this->getURL();
    $this->assertEqual($edit_url, $actual_url, 'On edit page.');

    // Check that the title and body fields are displayed with the correct values.
    $active = '<span class="visually-hidden">' . t('(active tab)') . '</span>';
    $link_text = t('!local-task-title!active', array('!local-task-title' => t('Edit'), '!active' => $active));
    $this->assertText(strip_tags($link_text), 0, 'Edit tab found and marked active.');
    $this->assertFieldByName($title_key, $edit[$title_key], 'Title field displayed.');
    $this->assertFieldByName($body_key, $edit[$body_key], 'Body field displayed.');

    // Edit the content of the node.
    $edit = array();
    $edit[$title_key] = $this->randomName(8);
    $edit[$body_key] = $this->randomName(16);
    // Stay on the current page, without reloading.
    $this->drupalPost(NULL, $edit, t('Save'));

    // Check that the title and body fields are displayed with the updated values.
    $this->assertText($edit[$title_key], 'Title displayed.');
    $this->assertText($edit[$body_key], 'Body displayed.');

    // Login as a second administrator user.
    $second_web_user = $this->drupalCreateUser(array('administer nodes', 'edit any page content'));
    $this->drupalLogin($second_web_user);
    // Edit the same node, creating a new revision.
    $this->drupalGet("node/$node->nid/edit");
    $edit = array();
    $edit['title'] = $this->randomName(8);
    $edit[$body_key] = $this->randomName(16);
    $edit['revision'] = TRUE;
    $this->drupalPost(NULL, $edit, t('Save and keep published'));

    // Ensure that the node revision has been created.
    $revised_node = $this->drupalGetNodeByTitle($edit['title'], TRUE);
    $this->assertNotIdentical($node->vid, $revised_node->vid, 'A new revision has been created.');
    // Ensure that the node author is preserved when it was not changed in the
    // edit form.
    $this->assertIdentical($node->uid, $revised_node->uid, 'The node author has been preserved.');
    // Ensure that the revision authors are different since the revisions were
    // made by different users.
    $first_node_version = node_revision_load($node->vid);
    $second_node_version = node_revision_load($revised_node->vid);
    $this->assertNotIdentical($first_node_version->revision_uid, $second_node_version->revision_uid, 'Each revision has a distinct user.');
  }

  /**
   * Tests changing a node's "authored by" field.
   */
  function testPageAuthoredBy() {
    $this->drupalLogin($this->admin_user);

    // Create node to edit.
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    $body_key = "body[$langcode][0][value]";
    $edit = array();
    $edit['title'] = $this->randomName(8);
    $edit[$body_key] = $this->randomName(16);
    $this->drupalPost('node/add/page', $edit, t('Save and publish'));

    // Check that the node was authored by the currently logged in user.
    $node = $this->drupalGetNodeByTitle($edit['title']);
    $this->assertIdentical($node->uid, $this->admin_user->id(), 'Node authored by admin user.');

    // Try to change the 'authored by' field to an invalid user name.
    $edit = array(
      'name' => 'invalid-name',
    );
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $this->assertText('The username invalid-name does not exist.');

    // Change the authored by field to an empty string, which should assign
    // authorship to the anonymous user (uid 0).
    $edit['name'] = '';
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $node = node_load($node->id(), TRUE);
    $this->assertIdentical($node->uid, '0', 'Node authored by anonymous user.');

    // Change the authored by field to another user's name (that is not
    // logged in).
    $edit['name'] = $this->web_user->getUsername();
    $this->drupalPost('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $node = node_load($node->nid, TRUE);
    $this->assertIdentical($node->uid, $this->web_user->id(), 'Node authored by normal user.');

    // Check that normal users cannot change the authored by information.
    $this->drupalLogin($this->web_user);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertNoFieldByName('name');
  }
}
