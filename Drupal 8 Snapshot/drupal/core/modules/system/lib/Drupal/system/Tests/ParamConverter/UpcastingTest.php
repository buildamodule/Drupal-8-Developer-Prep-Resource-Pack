<?php

/**
 * @file
 * Contains Drupal\system\Tests\ParamConverter\UpcastingTest.
 */

namespace Drupal\system\Tests\ParamConverter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\simpletest\WebTestBase;

/**
 * Web tests for the upcasting.
 */
class UpcastingTest extends WebTestBase {

  /**
   * Implement getInfo().
   */
  public static function getInfo() {
    return array(
      'name' => 'Upcasting tests',
      'description' => 'Tests upcasting of url arguments to entities.',
      'group' => 'ParamConverter',
    );
  }

  public static $modules = array('paramconverter_test');

  /**
   * Confirms that all parameters are converted as expected.
   *
   * All of these requests end up being proccessed by a controller with this
   * the signature: f($user, $node, $foo) returning either values or labels
   * like "user: Dries, node: First post, foo: bar"
   *
   * The tests shuffle the parameters around an checks if the right thing is
   * happening.
   */
  public function testUpcasting() {
    $node = $this->drupalCreateNode(array('title' => $this->randomName(8)));
    $user = $this->drupalCreateUser(array('access content'));
    $foo = 'bar';

    // paramconverter_test/test_user_node_foo/{user}/{node}/{foo}
    $this->drupalGet("paramconverter_test/test_user_node_foo/"  . $user->id() . '/' . $node->id() . "/$foo");
    $this->assertRaw("user: {$user->label()}, node: {$node->label()}, foo: $foo", 'user and node upcast by entity name');

    // paramconverter_test/test_node_user_user/{node}/{foo}/{user}
    // options.parameters.foo.type = entity:user
    $this->drupalGet("paramconverter_test/test_node_user_user/{$node->nid}/" . $user->id() . "/" . $user->id());
    $this->assertRaw("user: {$user->label()}, node: {$node->label()}, foo: {$user->label()}", 'foo converted to user as well');

    // paramconverter_test/test_node_node_foo/{user}/{node}/{foo}
    // options.parameters.user.type = entity:node
    $this->drupalGet("paramconverter_test/test_node_node_foo/{$node->nid}/{$node->nid}/$foo");
    $this->assertRaw("user: {$node->label()}, node: {$node->label()}, foo: $foo", 'user is upcast to node (rather than to user)');
  }

  /**
   * Confirms we can upcast to controller arguments of the same type.
   */
  public function testSameTypes() {
    $node = $this->drupalCreateNode(array('title' => $this->randomName(8)));
    $parent = $this->drupalCreateNode(array('title' => $this->randomName(8)));
    // paramconverter_test/node/{node}/set/parent/{parent}
    // options.parameters.parent.type = entity:node
    $this->drupalGet("paramconverter_test/node/" . $node->nid . "/set/parent/" . $parent->nid);
    $this->assertRaw("Setting '" . $parent->title . "' as parent of '" . $node->title . "'.");
  }
}
