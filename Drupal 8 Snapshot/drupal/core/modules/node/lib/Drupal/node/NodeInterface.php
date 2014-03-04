<?php

/**
 * @file
 * Contains \Drupal\node\Plugin\Core\Entity\NodeInterface.
 */

namespace Drupal\node;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface defining a node entity.
 */
interface NodeInterface extends ContentEntityInterface {

  /**
   * Sets the node title.
   *
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\NodeInterface
   *   The called node entity.
   */
  public function setTitle($title);

  /**
   * Returns the node creation timestamp.
   *
   * @return int
   *   Creation timestamp of the node.
   */
  public function getCreatedTime();

  /**
   * Sets the node creation timestamp.
   *
   * @param int $timestamp
   *   The node creation timestamp.
   *
   * @return \Drupal\node\NodeInterface
   *   The called node entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the node modification timestamp.
   *
   * @return int
   *   Node creation timestamp.
   */
  public function getChangedTime();

  /**
   * Returns the node promotion status.
   *
   * @return bool
   *   TRUE if the node is promoted.
   */
  public function isPromoted();

  /**
   * Sets the node promoted status.
   *
   * @param bool $promoted
   *   TRUE to set this node to promoted, FALSE to set it to not promoted.
   *
   * @return \Drupal\node\NodeInterface
   *   The called node entity.
   */
  public function setPromoted($promoted);

  /**
   * Returns the node sticky status.
   *
   * @return bool
   *   TRUE if the node is sticky.
   */
  public function isSticky();

  /**
   * Sets the node sticky status.
   *
   * @param bool $sticky
   *   TRUE to set this node to sticky, FALSE to set it to not sticky.
   *
   * @return \Drupal\node\NodeInterface
   *   The called node entity.
   */
  public function setSticky($sticky);

  /**
   * Returns the node author user entity.
   *
   * @return \Drupal\user\UserInterface
   *   The author user entity.
   */
  public function getAuthor();

  /**
   * Returns the node author user ID.
   *
   * @return int
   *   The author user ID.
   */
  public function getAuthorId();

  /**
   * Sets the node author user ID.
   *
   * @param int $uid
   *   The author user id.
   *
   * @return \Drupal\node\NodeInterface
   *   The called node entity.
   */
  public function setAuthorId($uid);

  /**
   * Returns the node published status indicator.
   *
   * Unpublished nodes are only visible to their authors and to administrators.
   *
   * @return bool
   *   TRUE if the node is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a node..
   *
   * @param bool $published
   *   TRUE to set this node to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\node\NodeInterface
   *   The called node entity.
   */
  public function setPublished($published);

  /**
   * Returns the node revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the node revision creation timestamp.
   *
   * @param int $imestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\node\NodeInterface
   *   The called node entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Returns the node revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionAuthor();

  /**
   * Sets the node revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\node\NodeInterface
   *   The called node entity.
   */
  public function setRevisionAuthorId($uid);

}
