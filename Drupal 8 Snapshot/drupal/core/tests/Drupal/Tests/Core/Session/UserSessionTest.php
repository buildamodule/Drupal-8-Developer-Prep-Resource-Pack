<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Session\UserSessionTest.
 */

namespace Drupal\Tests\Core\Session;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\UserSession;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the user session object.
 *
 * @see \Drupal\Core\Session\UserSession
 */
class UserSessionTest extends UnitTestCase {

  /**
   * The user sessions used in the test
   *
   * @var \Drupal\Core\Session\AccountInterface[]
   */
  protected $users = array();

  public static function getInfo() {
    return array(
      'name' => 'User session object',
      'description' => 'Tests the user session object.',
      'group' => 'Session',
    );
  }

  /**
   * Provides test data for getHasPermission().
   *
   * @return array
   */
  public function providerTestHasPermission() {
    $data = array();
    $data[] = array('example permission', array('user_one', 'user_two'), array('user_last'));
    $data[] = array('another example permission', array('user_two'), array('user_one', 'user_last'));
    $data[] = array('final example permission', array(), array('user_one', 'user_two', 'user_last'));

    return $data;
  }

  /**
   * Setups a user session for the test.
   *
   * @param array $rids
   *   The rids of the user.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The created user session.
   */
  protected function createUserSession(array $rids = array()) {
    return new UserSession(array('roles' => $rids));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $roles = array();
    $roles['role_one'] = $this->getMockBuilder('Drupal\user\Plugin\Core\Entity\Role')
      ->disableOriginalConstructor()
      ->setMethods(array('hasPermission'))
      ->getMock();
    $roles['role_one']->expects($this->any())
      ->method('hasPermission')
      ->will($this->returnValueMap(array(
        array('example permission', TRUE),
        array('another example permission', FALSE),
        array('last example permission', FALSE),
      )));

    $roles['role_two'] = $this->getMockBuilder('Drupal\user\Plugin\Core\Entity\Role')
      ->disableOriginalConstructor()
      ->setMethods(array('hasPermission'))
      ->getMock();
    $roles['role_two']->expects($this->any())
      ->method('hasPermission')
      ->will($this->returnValueMap(array(
        array('example permission', TRUE),
        array('another example permission', TRUE),
        array('last example permission', FALSE),
      )));

    $role_storage = $this->getMockBuilder('Drupal\user\RoleStorageController')
      ->disableOriginalConstructor()
      ->setMethods(array('loadMultiple'))
      ->getMock();
    $role_storage->expects($this->any())
      ->method('loadMultiple')
      ->will($this->returnValueMap(array(
        array(array(), array()),
        array(NULL, $roles),
        array(array('role_one'), array($roles['role_one'])),
        array(array('role_two'), array($roles['role_two'])),
        array(array('role_one', 'role_two'), array($roles['role_one'], $roles['role_two'])),
      )));

    $entity_manager = $this->getMockBuilder('Drupal\Core\Entity\EntityManager')
      ->disableOriginalConstructor()
      ->getMock();
    $entity_manager->expects($this->any())
      ->method('getStorageController')
      ->with($this->equalTo('user_role'))
      ->will($this->returnValue($role_storage));
    $container = new ContainerBuilder();
    $container->set('plugin.manager.entity', $entity_manager);
    \Drupal::setContainer($container);

    $this->users['user_one'] = $this->createUserSession(array('role_one'));
    $this->users['user_two'] = $this->createUserSession(array('role_one', 'role_two'));
    $this->users['user_last'] = $this->createUserSession();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    parent::tearDown();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Tests the has permission method.
   *
   * @param string $permission
   *   The permission to check.
   * @param \Drupal\Core\Session\AccountInterface[] $sessions_with_access
   *   The users with access.
   * @param \Drupal\Core\Session\AccountInterface[] $sessions_without_access
   *   The users without access.
   *
   * @dataProvider providerTestHasPermission
   *
   * @see \Drupal\Core\Session\UserSession::hasPermission().
   */
  public function testHasPermission($permission, array $sessions_with_access, array $sessions_without_access) {
    foreach ($sessions_with_access as $name) {
      $this->assertTrue($this->users[$name]->hasPermission($permission));
    }
    foreach ($sessions_without_access as $name) {
      $this->assertFalse($this->users[$name]->hasPermission($permission));
    }
  }

}
