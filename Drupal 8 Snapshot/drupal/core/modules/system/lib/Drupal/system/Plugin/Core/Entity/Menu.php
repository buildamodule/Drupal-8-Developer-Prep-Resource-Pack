<?php

/**
 * @file
 * Contains \Drupal\system\Plugin\Core\Entity\Menu.
 */

namespace Drupal\system\Plugin\Core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Annotation\Translation;
use Drupal\system\MenuInterface;

/**
 * Defines the Menu configuration entity class.
 *
 * @EntityType(
 *   id = "menu",
 *   label = @Translation("Menu"),
 *   module = "system",
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigStorageController",
 *     "access" = "Drupal\system\MenuAccessController"
 *   },
 *   config_prefix = "menu.menu",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class Menu extends ConfigEntityBase implements MenuInterface {

  /**
   * The menu machine name.
   *
   * @var string
   */
  public $id;

  /**
   * The menu UUID.
   *
   * @var string
   */
  public $uuid;

  /**
   * The human-readable name of the menu entity.
   *
   * @var string
   */
  public $label;

  /**
   * The menu description.
   *
   * @var string
   */
  public $description;

}
