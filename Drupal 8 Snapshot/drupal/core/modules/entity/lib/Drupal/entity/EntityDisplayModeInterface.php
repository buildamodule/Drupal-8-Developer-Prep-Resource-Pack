<?php

/**
 * @file
 * Contains \Drupal\entity\EntityDisplayModeInterface.
 */

namespace Drupal\entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for entity types that hold form and view mode settings.
 */
interface EntityDisplayModeInterface extends ConfigEntityInterface {

  /**
   * Returns the entity type this display mode is used for.
   *
   * @return string
   *   The entity type name.
   */
  public function getTargetType();

}
