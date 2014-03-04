<?php

/**
 * @file
 * Contains \Drupal\user\UserInterface.
 */

namespace Drupal\user;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an interface defining a user entity.
 */
interface UserInterface extends EntityInterface, AccountInterface {

  /**
   * Returns a list of roles.
   *
   * @return array
   *   List of role IDs.
   */
  public function getRoles();

  /**
   * Whether a user has a certain role.
   *
   * @param string $rid
   *   The role ID to check.
   *
   * @return bool
   *   Returns TRUE if the user has the role, otherwise FALSE.
   */
  public function hasRole($rid);

  /**
   * Add a role to a user.
   *
   * @param string $rid
   *   The role ID to add.
   */
  public function addRole($rid);

  /**
   * Remove a role from a user.
   *
   * @param string $rid
   *   The role ID to remove.
   */
  public function removeRole($rid);

  /**
   * Checks whether a user has a certain permission.
   *
   * @param string $permission
   *   The permission string to check.
   *
   * @return bool
   *   TRUE if the user has the permission, FALSE otherwise.
   */
  public function hasPermission($permission);

  /**
   * Sets the username of this account.
   *
   * @param string $username
   *   The new user name.
   *
   * @return \Drupal\user\UserInterface
   *   The called user entity.
   */
  public function setUsername($username);

  /**
   * Returns the hashed password.
   *
   * @return string
   *   The hashed password.
   */
  public function getPassword();

  /**
   * Sets the user password.
   *
   * @param string $password
   *   The new unhashed password.
   *
   * @return \Drupal\user\UserInterface
   *   The called user entity.
   */
  public function setPassword($password);

  /**
   * Sets the e-mail address of the user.
   *
   * @param string $mail
   *   The new e-mail address of the user.
   *
   * @return \Drupal\user\UserInterface
   *   The called user entity.
   */
  public function setEmail($mail);

  /**
   * Returns the default theme of the user.
   *
   * @return string
   *   Name of the theme.
   */
  public function getDefaultTheme();

  /**
   * Returns the user signature.
   *
   * @todo: Convert this to a configurable field.
   *
   * @return string
   *   The signature text.
   */
  public function getSignature();

  /**
   * Returns the signature format.
   *
   * @return string
   *   Name of the filter format.
   */
  public function getSignatureFormat();

  /**
   * Returns the creation time of the user as a UNIX timestamp.
   *
   * @return int
   *   Timestamp of the creation date.
   */
  public function getCreatedTime();

  /**
   * Sets the UNIX timestamp when the user last accessed the site..
   *
   * @param int $timestamp
   *   Timestamp of the last access.
   *
   * @return \Drupal\user\UserInterface
   *   The called user entity.
   */
  public function setLastAccessTime($timestamp);

  /**
   * Returns the UNIX timestamp when the user last logged in.
   *
   * @return int
   *   Timestamp of the last login time.
   */
  public function getLastLoginTime();

  /**
   * Sets the UNIX timestamp when the user last logged in.
   *
   * @param int $timestamp
   *   Timestamp of the last login time.
   *
   * @return \Drupal\user\UserInterface
   *   The called user entity.
   */
  public function setLastLoginTime($timestamp);

  /**
   * Returns TRUE if the user is active.
   *
   * @return bool
   *   TRUE if the user is active, false otherwise.
   */
  public function isActive();

  /**
   * Returns TRUE if the user is blocked.
   *
   * @return bool
   *   TRUE if the user is blocked, false otherwise.
   */
  public function isBlocked();

  /**
   * Activates the user.
   *
   * @return \Drupal\user\UserInterface
   *   The called user entity.
   */
  public function activate();

  /**
   * Blocks the user.
   *
   * @return \Drupal\user\UserInterface
   *   The called user entity.
   */
  public function block();

  /**
   * Returns the e-mail that was used when the user was registered.
   *
   * @return string
   *   Initial e-mail address of the user.
   */
  public function getInitialEmail();

}
