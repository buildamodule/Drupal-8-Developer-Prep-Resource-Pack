<?php

/**
 * @file
 * Contains \Drupal\system\Tests\Entity\EntityUnitTestBase.
 */

namespace Drupal\system\Tests\Entity;

use Drupal\simpletest\DrupalUnitTestBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines an abstract test base for entity unit tests.
 */
abstract class EntityUnitTestBase extends DrupalUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('entity', 'user', 'system', 'field', 'text', 'field_sql_storage', 'entity_test');

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $state;

  public function setUp() {
    parent::setUp();

    $this->entityManager = $this->container->get('plugin.manager.entity');
    $this->state = $this->container->get('state');

    $this->installSchema('user', 'users');
    $this->installSchema('system', 'sequences');
    $this->installSchema('entity_test', 'entity_test');
    $this->installConfig(array('field'));
  }

  /**
   * Creates a user.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   * @param array $permissions
   *   (optional) Array of permission names to assign to user. The
   *   users_roles tables must be installed before this can be used.
   *
   * @return \Drupal\user\Plugin\Core\Entity\User
   *   The created user entity.
   */
  protected function createUser($values = array(), $permissions = array()) {
    if ($permissions) {
      // Create a new role and apply permissions to it.
      $role = entity_create('user_role', array(
        'id' => strtolower($this->randomName(8)),
        'label' => $this->randomName(8),
      ));
      $role->save();
      user_role_grant_permissions($role->id(), $permissions);
      $values['roles'][] = $role->id();
    }

    $account = entity_create('user', $values + array(
      'name' => $this->randomName(),
      'status' => 1,
    ));
    $account->enforceIsNew();
    $account->save();
    return $account;
  }

  /**
   * Reloads the given entity from the storage and returns it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be reloaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The reloaded entity.
   */
  protected function reloadEntity(EntityInterface $entity) {
    $controller = $this->entityManager->getStorageController($entity->entityType());
    $controller->resetCache(array($entity->id()));
    return $controller->load($entity->id());
  }

  /**
   * Returns the entity_test hook invocation info.
   *
   * @return array
   *   An associative array of arbitrary hook data keyed by hook name.
   */
  protected function getHooksInfo() {
    $key = 'entity_test.hooks';
    $hooks = $this->state->get($key);
    $this->state->set($key, array());
    return $hooks;
  }

}
