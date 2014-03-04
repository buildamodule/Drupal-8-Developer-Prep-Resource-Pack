<?php

/**
 * @file
 * Contains \Drupal\Core\Entity\Plugin\DataType\Entity.
 */

namespace Drupal\Core\Entity\Plugin\DataType;

use Drupal\Core\TypedData\Annotation\DataType;
use Drupal\Core\Annotation\Translation;

/**
 * Defines the base plugin for deriving data types for entity types.
 *
 * Note that the class only registers the plugin, and is actually never used.
 * \Drupal\Core\Entity\Entity is available for use as base class.
 *
 * @DataType(
 *   id = "entity",
 *   label = @Translation("Entity"),
 *   description = @Translation("All kind of entities, e.g. nodes, comments or users."),
 *   derivative = "\Drupal\Core\Entity\Plugin\DataType\Deriver\EntityDeriver"
 * )
 */
abstract class Entity {

}
