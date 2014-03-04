<?php

/**
 * @file
 * Definition of Drupal\user\Tests\UserPermissionsTest.
 */

namespace Drupal\user\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\user\RoleStorageController;

class UserPermissionsTest extends WebTestBase {
  protected $admin_user;
  protected $rid;

  public static function getInfo() {
    return array(
      'name' => 'Role permissions',
      'description' => 'Verify that role permissions can be added and removed via the permissions page.',
      'group' => 'User'
    );
  }

  function setUp() {
    parent::setUp();

    $this->admin_user = $this->drupalCreateUser(array('administer permissions', 'access user profiles', 'administer site configuration', 'administer modules', 'administer users'));

    // Find the new role ID.
    $all_rids = $this->admin_user->getRoles();
    unset($all_rids[array_search(DRUPAL_AUTHENTICATED_RID, $all_rids)]);
    $this->rid = reset($all_rids);
  }

  /**
   * Change user permissions and check user_access().
   */
  function testUserPermissionChanges() {
    $this->drupalLogin($this->admin_user);
    $rid = $this->rid;
    $account = $this->admin_user;

    // Add a permission.
    $this->assertFalse(user_access('administer nodes', $account), 'User does not have "administer nodes" permission.');
    $edit = array();
    $edit[$rid . '[administer nodes]'] = TRUE;
    $this->drupalPost('admin/people/permissions', $edit, t('Save permissions'));
    $this->assertText(t('The changes have been saved.'), 'Successful save message displayed.');
    $storage_controller = $this->container->get('plugin.manager.entity')->getStorageController('user_role');
    $storage_controller->resetCache();
    $this->assertTrue(user_access('administer nodes', $account), 'User now has "administer nodes" permission.');

    // Remove a permission.
    $this->assertTrue(user_access('access user profiles', $account), 'User has "access user profiles" permission.');
    $edit = array();
    $edit[$rid . '[access user profiles]'] = FALSE;
    $this->drupalPost('admin/people/permissions', $edit, t('Save permissions'));
    $this->assertText(t('The changes have been saved.'), 'Successful save message displayed.');
    $storage_controller->resetCache();
    $this->assertFalse(user_access('access user profiles', $account), 'User no longer has "access user profiles" permission.');
  }

  /**
   * Test assigning of permissions for the administrator role.
   */
  function testAdministratorRole() {
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config/people/accounts');

    // Set the user's role to be the administrator role.
    $edit = array();
    $edit['user_admin_role'] = $this->rid;
    $this->drupalPost('admin/config/people/accounts', $edit, t('Save configuration'));

    // Enable aggregator module and ensure the 'administer news feeds'
    // permission is assigned by default.
    $edit = array();
    $edit['modules[Core][aggregator][enable]'] = TRUE;
    // Aggregator depends on file module, enable that as well.
    $edit['modules[Core][file][enable]'] = TRUE;
    $this->drupalPost('admin/modules', $edit, t('Save configuration'));
    $this->assertTrue(user_access('administer news feeds', $this->admin_user), 'The permission was automatically assigned to the administrator role');
  }

  /**
   * Verify proper permission changes by user_role_change_permissions().
   */
  function testUserRoleChangePermissions() {
    $rid = $this->rid;
    $account = $this->admin_user;

    // Verify current permissions.
    $this->assertFalse(user_access('administer nodes', $account), 'User does not have "administer nodes" permission.');
    $this->assertTrue(user_access('access user profiles', $account), 'User has "access user profiles" permission.');
    $this->assertTrue(user_access('administer site configuration', $account), 'User has "administer site configuration" permission.');

    // Change permissions.
    $permissions = array(
      'administer nodes' => 1,
      'access user profiles' => 0,
    );
    user_role_change_permissions($rid, $permissions);

    // Verify proper permission changes.
    $this->assertTrue(user_access('administer nodes', $account), 'User now has "administer nodes" permission.');
    $this->assertFalse(user_access('access user profiles', $account), 'User no longer has "access user profiles" permission.');
    $this->assertTrue(user_access('administer site configuration', $account), 'User still has "administer site configuration" permission.');
  }
}
