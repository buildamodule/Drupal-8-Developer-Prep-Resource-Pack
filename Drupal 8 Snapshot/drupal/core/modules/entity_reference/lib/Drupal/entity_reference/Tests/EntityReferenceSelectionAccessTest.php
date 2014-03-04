<?php

/**
 * @file
 * Contains \Drupal\entity_reference\Tests\EntityReferenceSelectionAccessTest.
 */

namespace Drupal\entity_reference\Tests;

use Drupal\Core\Entity\Field\FieldDefinitionInterface;
use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the Entity Reference Selection plugin.
 */
class EntityReferenceSelectionAccessTest extends WebTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Entity Reference handlers',
      'description' => 'Tests for the base handlers provided by Entity Reference.',
      'group' => 'Entity Reference',
    );
  }

  public static $modules = array('node', 'comment', 'entity_reference');

  function setUp() {
    parent::setUp();

    // Create an Article node type.
    $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
  }

  protected function assertReferenceable(FieldDefinitionInterface $field_definition, $tests, $handler_name) {
    $handler = \Drupal::service('plugin.manager.entity_reference.selection')->getSelectionHandler($field_definition);

    foreach ($tests as $test) {
      foreach ($test['arguments'] as $arguments) {
        $result = call_user_func_array(array($handler, 'getReferenceableEntities'), $arguments);
        $this->assertEqual($result, $test['result'], format_string('Valid result set returned by @handler.', array('@handler' => $handler_name)));

        $result = call_user_func_array(array($handler, 'countReferenceableEntities'), $arguments);
        if (!empty($test['result'])) {
          $bundle = key($test['result']);
          $count = count($test['result'][$bundle]);
        }
        else {
          $count = 0;
        }

        $this->assertEqual($result, $count, format_string('Valid count returned by @handler.', array('@handler' => $handler_name)));
      }
    }
  }

  /**
   * Test the node-specific overrides of the entity handler.
   */
  public function testNodeHandler() {
    // Create a field and instance.
    $field = entity_create('field_entity', array(
      'translatable' => FALSE,
      'entity_types' => array(),
      'settings' => array(
        'target_type' => 'node',
      ),
      'field_name' => 'test_field',
      'type' => 'entity_reference',
      'cardinality' => '1',
    ));
    $field->save();
    $instance = entity_create('field_instance', array(
      'field_name' => 'test_field',
      'entity_type' => 'test_entity',
      'bundle' => 'test_bundle',
      'settings' => array(
        'handler' => 'default',
        'handler_settings' => array(
          'target_bundles' => array(),
        ),
      ),
    ));
    $instance->save();

    // Build a set of test data.
    // Titles contain HTML-special characters to test escaping.
    $node_values = array(
      'published1' => array(
        'type' => 'article',
        'status' => NODE_PUBLISHED,
        'title' => 'Node published1 (<&>)',
        'uid' => 1,
      ),
      'published2' => array(
        'type' => 'article',
        'status' => NODE_PUBLISHED,
        'title' => 'Node published2 (<&>)',
        'uid' => 1,
      ),
      'unpublished' => array(
        'type' => 'article',
        'status' => NODE_NOT_PUBLISHED,
        'title' => 'Node unpublished (<&>)',
        'uid' => 1,
      ),
    );

    $nodes = array();
    $node_labels = array();
    foreach ($node_values as $key => $values) {
      $node = entity_create('node', $values);
      $node->save();
      $nodes[$key] = $node;
      $node_labels[$key] = check_plain($node->label());
    }

    // Test as a non-admin.
    $normal_user = $this->drupalCreateUser(array('access content'));
    $request = $this->container->get('request');
    $request->attributes->set('_account', $normal_user);
    $referenceable_tests = array(
      array(
        'arguments' => array(
          array(NULL, 'CONTAINS'),
        ),
        'result' => array(
          'article' => array(
            $nodes['published1']->id() => $node_labels['published1'],
            $nodes['published2']->id() => $node_labels['published2'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('published1', 'CONTAINS'),
          array('Published1', 'CONTAINS'),
        ),
        'result' => array(
          'article' => array(
            $nodes['published1']->id() => $node_labels['published1'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('published2', 'CONTAINS'),
          array('Published2', 'CONTAINS'),
        ),
        'result' => array(
          'article' => array(
            $nodes['published2']->id() => $node_labels['published2'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('invalid node', 'CONTAINS'),
        ),
        'result' => array(),
      ),
      array(
        'arguments' => array(
          array('Node unpublished', 'CONTAINS'),
        ),
        'result' => array(),
      ),
    );
    $this->assertReferenceable($instance, $referenceable_tests, 'Node handler');

    // Test as an admin.
    $admin_user = $this->drupalCreateUser(array('access content', 'bypass node access'));
    $request->attributes->set('_account', $admin_user);
    $referenceable_tests = array(
      array(
        'arguments' => array(
          array(NULL, 'CONTAINS'),
        ),
        'result' => array(
          'article' => array(
            $nodes['published1']->id() => $node_labels['published1'],
            $nodes['published2']->id() => $node_labels['published2'],
            $nodes['unpublished']->id() => $node_labels['unpublished'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('Node unpublished', 'CONTAINS'),
        ),
        'result' => array(
          'article' => array(
            $nodes['unpublished']->id() => $node_labels['unpublished'],
          ),
        ),
      ),
    );
    $this->assertReferenceable($instance, $referenceable_tests, 'Node handler (admin)');
  }

  /**
   * Test the user-specific overrides of the entity handler.
   */
  public function testUserHandler() {
    // Create a field and instance.
    $field = entity_create('field_entity', array(
      'translatable' => FALSE,
      'entity_types' => array(),
      'settings' => array(
        'target_type' => 'user',
      ),
      'field_name' => 'test_field',
      'type' => 'entity_reference',
      'cardinality' => '1',
    ));
    $field->save();
    $instance = entity_create('field_instance', array(
      'field_name' => 'test_field',
      'entity_type' => 'test_entity',
      'bundle' => 'test_bundle',
      'settings' => array(
        'handler' => 'default',
        'handler_settings' => array(
          'target_bundles' => array(),
        ),
      ),
    ));
    $instance->save();

    // Build a set of test data.
    $user_values = array(
      'anonymous' => user_load(0),
      'admin' => user_load(1),
      'non_admin' => array(
        'name' => 'non_admin <&>',
        'mail' => 'non_admin@example.com',
        'roles' => array(),
        'pass' => user_password(),
        'status' => 1,
      ),
      'blocked' => array(
        'name' => 'blocked <&>',
        'mail' => 'blocked@example.com',
        'roles' => array(),
        'pass' => user_password(),
        'status' => 0,
      ),
    );

    $user_values['anonymous']->name = \Drupal::config('user.settings')->get('anonymous');
    $users = array();

    $user_labels = array();
    foreach ($user_values as $key => $values) {
      if (is_array($values)) {
        $account = entity_create('user', $values);
        $account->save();
      }
      else {
        $account = $values;
      }
      $users[$key] = $account;
      $user_labels[$key] = check_plain($account->getUsername());
    }

    // Test as a non-admin.
    $request = $this->container->get('request');
    $request->attributes->set('_account', $users['non_admin']);
    $referenceable_tests = array(
      array(
        'arguments' => array(
          array(NULL, 'CONTAINS'),
        ),
        'result' => array(
          'user' => array(
            $users['admin']->id() => $user_labels['admin'],
            $users['non_admin']->id() => $user_labels['non_admin'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('non_admin', 'CONTAINS'),
          array('NON_ADMIN', 'CONTAINS'),
        ),
        'result' => array(
          'user' => array(
            $users['non_admin']->id() => $user_labels['non_admin'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('invalid user', 'CONTAINS'),
        ),
        'result' => array(),
      ),
      array(
        'arguments' => array(
          array('blocked', 'CONTAINS'),
        ),
        'result' => array(),
      ),
    );
    $this->assertReferenceable($instance, $referenceable_tests, 'User handler');

    $request->attributes->set('_account', $users['admin']);
    $referenceable_tests = array(
      array(
        'arguments' => array(
          array(NULL, 'CONTAINS'),
        ),
        'result' => array(
          'user' => array(
            $users['anonymous']->id() => $user_labels['anonymous'],
            $users['admin']->id() => $user_labels['admin'],
            $users['non_admin']->id() => $user_labels['non_admin'],
            $users['blocked']->id() => $user_labels['blocked'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('blocked', 'CONTAINS'),
        ),
        'result' => array(
          'user' => array(
            $users['blocked']->id() => $user_labels['blocked'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('Anonymous', 'CONTAINS'),
          array('anonymous', 'CONTAINS'),
        ),
        'result' => array(
          'user' => array(
            $users['anonymous']->id() => $user_labels['anonymous'],
          ),
        ),
      ),
    );
    $this->assertReferenceable($instance, $referenceable_tests, 'User handler (admin)');
  }

  /**
   * Test the comment-specific overrides of the entity handler.
   */
  public function testCommentHandler() {
    // Create a field and instance.
    $field = entity_create('field_entity', array(
      'translatable' => FALSE,
      'entity_types' => array(),
      'settings' => array(
        'target_type' => 'comment',
      ),
      'field_name' => 'test_field',
      'type' => 'entity_reference',
      'cardinality' => '1',
    ));
    $field->save();
    $instance = entity_create('field_instance', array(
      'field_name' => 'test_field',
      'entity_type' => 'test_entity',
      'bundle' => 'test_bundle',
      'settings' => array(
        'handler' => 'default',
        'handler_settings' => array(
          'target_bundles' => array(),
        ),
      ),
    ));
    $instance->save();

    // Build a set of test data.
    $node_values = array(
      'published' => array(
        'type' => 'article',
        'status' => 1,
        'title' => 'Node published',
        'uid' => 1,
      ),
      'unpublished' => array(
        'type' => 'article',
        'status' => 0,
        'title' => 'Node unpublished',
        'uid' => 1,
      ),
    );
    $nodes = array();
    foreach ($node_values as $key => $values) {
      $node = entity_create('node', $values);
      $node->save();
      $nodes[$key] = $node;
    }

    $comment_values = array(
      'published_published' => array(
        'nid' => $nodes['published']->id(),
        'uid' => 1,
        'cid' => NULL,
        'pid' => 0,
        'status' => COMMENT_PUBLISHED,
        'subject' => 'Comment Published <&>',
        'language' => Language::LANGCODE_NOT_SPECIFIED,
      ),
      'published_unpublished' => array(
        'nid' => $nodes['published']->id(),
        'uid' => 1,
        'cid' => NULL,
        'pid' => 0,
        'status' => COMMENT_NOT_PUBLISHED,
        'subject' => 'Comment Unpublished <&>',
        'language' => Language::LANGCODE_NOT_SPECIFIED,
      ),
      'unpublished_published' => array(
        'nid' => $nodes['unpublished']->id(),
        'uid' => 1,
        'cid' => NULL,
        'pid' => 0,
        'status' => COMMENT_NOT_PUBLISHED,
        'subject' => 'Comment Published on Unpublished node <&>',
        'language' => Language::LANGCODE_NOT_SPECIFIED,
      ),
    );

    $comments = array();
    $comment_labels = array();
    foreach ($comment_values as $key => $values) {
      $comment = entity_create('comment', $values);
      $comment->save();
      $comments[$key] = $comment;
      $comment_labels[$key] = check_plain($comment->label());
    }

    // Test as a non-admin.
    $normal_user = $this->drupalCreateUser(array('access content', 'access comments'));
    $request = $this->container->get('request');
    $request->attributes->set('_account', $normal_user);
    $referenceable_tests = array(
      array(
        'arguments' => array(
          array(NULL, 'CONTAINS'),
        ),
        'result' => array(
          'comment_node_article' => array(
            $comments['published_published']->cid->value => $comment_labels['published_published'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('Published', 'CONTAINS'),
        ),
        'result' => array(
          'comment_node_article' => array(
            $comments['published_published']->cid->value => $comment_labels['published_published'],
          ),
        ),
      ),
      array(
        'arguments' => array(
          array('invalid comment', 'CONTAINS'),
        ),
        'result' => array(),
      ),
      array(
        'arguments' => array(
          array('Comment Unpublished', 'CONTAINS'),
        ),
        'result' => array(),
      ),
    );
    $this->assertReferenceable($instance, $referenceable_tests, 'Comment handler');

    // Test as a comment admin.
    $admin_user = $this->drupalCreateUser(array('access content', 'access comments', 'administer comments'));
    $request->attributes->set('_account', $admin_user);
    $referenceable_tests = array(
      array(
        'arguments' => array(
          array(NULL, 'CONTAINS'),
        ),
        'result' => array(
          'comment_node_article' => array(
            $comments['published_published']->cid->value => $comment_labels['published_published'],
            $comments['published_unpublished']->cid->value => $comment_labels['published_unpublished'],
          ),
        ),
      ),
    );
    $this->assertReferenceable($instance, $referenceable_tests, 'Comment handler (comment admin)');

    // Test as a node and comment admin.
    $admin_user = $this->drupalCreateUser(array('access content', 'access comments', 'administer comments', 'bypass node access'));
    $request->attributes->set('_account', $admin_user);
    $referenceable_tests = array(
      array(
        'arguments' => array(
          array(NULL, 'CONTAINS'),
        ),
        'result' => array(
          'comment_node_article' => array(
            $comments['published_published']->cid->value => $comment_labels['published_published'],
            $comments['published_unpublished']->cid->value => $comment_labels['published_unpublished'],
            $comments['unpublished_published']->cid->value => $comment_labels['unpublished_published'],
          ),
        ),
      ),
    );
    $this->assertReferenceable($instance, $referenceable_tests, 'Comment handler (comment + node admin)');
  }
}
