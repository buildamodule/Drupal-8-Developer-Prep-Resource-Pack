<?php

/**
 * @file
 * Contains Drupal\user\Tests\Views\HandlerFieldRoleTest.
 */

namespace Drupal\user\Tests\Views;

/**
 * Tests the role field handler.
 *
 * @see views_handler_field_user_name
 */
class HandlerFieldRoleTest extends UserTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_views_handler_field_role');

  public static function getInfo() {
    return array(
      'name' => 'User: Role Field',
      'description' => 'Tests the handler of the user: role field.',
      'group' => 'Views module integration',
    );
  }

  public function testRole() {
    // Create a couple of roles for the view.
    $rolename_a = 'a' . $this->randomName(8);
    $rid_a = $this->drupalCreateRole(array('access content'), $rolename_a, $rolename_a, 9);

    $rolename_b = 'b' . $this->randomName(8);
    $rid_b = $this->drupalCreateRole(array('access content'), $rolename_b, $rolename_b, 8);

    $rolename_not_assigned = $this->randomName(8);
    $this->drupalCreateRole(array('access content'), $rolename_not_assigned, $rolename_not_assigned);

    // Add roles to user 1.
    $user = entity_load('user', 1);
    $user->addRole($rolename_a);
    $user->addRole($rolename_b);
    $user->save();

    $view = views_get_view('test_views_handler_field_role');
    $this->executeView($view);
    $view->row_index = 0;
    // The role field is populated during preRender.
    $view->field['rid']->preRender($view->result);
    $render = $view->field['rid']->advancedRender($view->result[0]);

    $this->assertEqual($rolename_b . $rolename_a, $render, 'View test_views_handler_field_role renders role assigned to user in the correct order.');
    $this->assertFalse(strpos($render, $rolename_not_assigned), 'View test_views_handler_field_role does not render a role not assigned to a user.');
  }

}
