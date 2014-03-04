<?php

/**
 * @file
 * Contains \Drupal\contextual\Tests\ContextualDynamicContextTest.
 */

namespace Drupal\contextual\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests accessible links after inaccessible links on dynamic context.
 */
class ContextualDynamicContextTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('contextual', 'node', 'views', 'views_ui');

  public static function getInfo() {
    return array(
      'name' => 'Contextual links on node lists',
      'description' => 'Tests if contextual links are showing on the front page depending on permissions.',
      'group' => 'Contextual',
    );
  }

  function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
    $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));

    $this->editor_user = $this->drupalCreateUser(array('access content', 'access contextual links', 'edit any article content'));
    $this->authenticated_user = $this->drupalCreateUser(array('access content', 'access contextual links'));
    $this->anonymous_user = $this->drupalCreateUser(array('access content'));
  }

  /**
   * Tests contextual links with different permissions.
   *
   * Ensures that contextual link placeholders always exist, even if the user is
   * not allowed to use contextual links.
   */
  function testDifferentPermissions() {
    $this->drupalLogin($this->editor_user);

    // Create three nodes in the following order:
    // - An article, which should be user-editable.
    // - A page, which should not be user-editable.
    // - A second article, which should also be user-editable.
    $node1 = $this->drupalCreateNode(array('type' => 'article', 'promote' => 1));
    $node2 = $this->drupalCreateNode(array('type' => 'page', 'promote' => 1));
    $node3 = $this->drupalCreateNode(array('type' => 'article', 'promote' => 1));

    // Now, on the front page, all article nodes should have contextual links
    // placeholders, as should the view that contains them.
    $ids = array(
      'node:node:' . $node1->id() . ':',
      'node:node:' . $node2->id() . ':',
      'node:node:' . $node3->id() . ':',
      'views_ui:admin/structure/views/view:frontpage:location=page&name=frontpage&display_id=page_1',
    );

    // Editor user: can access contextual links and can edit articles.
    $this->drupalGet('node');
    for ($i = 0; $i < count($ids); $i++) {
      $this->assertContextualLinkPlaceHolder($ids[$i]);
    }
    $this->renderContextualLinks(array(), 'node');
    $this->assertResponse(400);
    $this->assertRaw('No contextual ids specified.');
    $response = $this->renderContextualLinks($ids, 'node');
    $this->assertResponse(200);
    $json = drupal_json_decode($response);
    $this->assertIdentical($json[$ids[0]], '<ul class="contextual-links"><li class="node-edit odd first last"><a href="' . base_path() . 'node/1/edit?destination=node">Edit</a></li></ul>');
    $this->assertIdentical($json[$ids[1]], '');
    $this->assertIdentical($json[$ids[2]], '<ul class="contextual-links"><li class="node-edit odd first last"><a href="' . base_path() . 'node/3/edit?destination=node">Edit</a></li></ul>');
    $this->assertIdentical($json[$ids[3]], '');

    // Authenticated user: can access contextual links, cannot edit articles.
    $this->drupalLogin($this->authenticated_user);
    $this->drupalGet('node');
    for ($i = 0; $i < count($ids); $i++) {
      $this->assertContextualLinkPlaceHolder($ids[$i]);
    }
    $this->renderContextualLinks(array(), 'node');
    $this->assertResponse(400);
    $this->assertRaw('No contextual ids specified.');
    $response = $this->renderContextualLinks($ids, 'node');
    $this->assertResponse(200);
    $json = drupal_json_decode($response);
    $this->assertIdentical($json[$ids[0]], '');
    $this->assertIdentical($json[$ids[1]], '');
    $this->assertIdentical($json[$ids[2]], '');
    $this->assertIdentical($json[$ids[3]], '');

    // Anonymous user: cannot access contextual links.
    $this->drupalLogin($this->anonymous_user);
    $this->drupalGet('node');
    for ($i = 0; $i < count($ids); $i++) {
      $this->assertContextualLinkPlaceHolder($ids[$i]);
    }
    $this->renderContextualLinks(array(), 'node');
    $this->assertResponse(403);
    $this->renderContextualLinks($ids, 'node');
    $this->assertResponse(403);
  }

  /**
   * Asserts that a contextual link placeholder with the given id exists.
   *
   * @param string $id
   *   A contextual link id.
   *
   * @return bool
   */
  protected function assertContextualLinkPlaceHolder($id) {
    $this->assertRaw('<div data-contextual-id="'. $id . '"></div>', format_string('Contextual link placeholder with id @id exists.', array('@id' => $id)));
  }

  /**
   * Asserts that a contextual link placeholder with the given id does not exist.
   *
   * @param string $id
   *   A contextual link id.
   *
   * @return bool
   */
  protected function assertNoContextualLinkPlaceHolder($id) {
    $this->assertNoRaw('<div data-contextual-id="'. $id . '"></div>', format_string('Contextual link placeholder with id @id does not exist.', array('@id' => $id)));
  }

  /**
   * Get server-rendered contextual links for the given contextual link ids.
   *
   * @param array $ids
   *   An array of contextual link ids.
   * @param string $current_path
   *   The Drupal path for the page for which the contextual links are rendered.
   *
   * @return string
   *   The response body.
   */
  protected function renderContextualLinks($ids, $current_path) {
    // Build POST values.
    $post = array();
    for ($i = 0; $i < count($ids); $i++) {
      $post['ids[' . $i . ']'] = $ids[$i];
    }

    // Serialize POST values.
    foreach ($post as $key => $value) {
      // Encode according to application/x-www-form-urlencoded
      // Both names and values needs to be urlencoded, according to
      // http://www.w3.org/TR/html4/interact/forms.html#h-17.13.4.1
      $post[$key] = urlencode($key) . '=' . urlencode($value);
    }
    $post = implode('&', $post);

    // Perform HTTP request.
    return $this->curlExec(array(
      CURLOPT_URL => url('contextual/render', array('absolute' => TRUE, 'query' => array('destination' => $current_path))),
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => $post,
      CURLOPT_HTTPHEADER => array(
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
      ),
    ));
  }
}
