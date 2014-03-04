<?php

/**
 * @file
 * Contains \Drupal\entity\Plugin\Core\Entity\EntityDisplay.
 */

namespace Drupal\entity\Plugin\Core\Entity;

use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Annotation\Translation;
use Drupal\entity\EntityDisplayBase;
use Drupal\entity\EntityDisplayInterface;

/**
 * Configuration entity that contains display options for all components of a
 * rendered entity in a given view mode.
 *
 * @EntityType(
 *   id = "entity_display",
 *   label = @Translation("Entity display"),
 *   module = "entity",
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigStorageController"
 *   },
 *   config_prefix = "entity.display",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class EntityDisplay extends EntityDisplayBase implements EntityDisplayInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    $this->pluginManager = \Drupal::service('plugin.manager.field.formatter');
    $this->displayContext = 'display';

    parent::__construct($values, $entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderer($field_name) {
    if (isset($this->plugins[$field_name])) {
      return $this->plugins[$field_name];
    }

    // Instantiate the formatter object from the stored display properties.
    if ($configuration = $this->getComponent($field_name)) {
      $instance = field_info_instance($this->targetEntityType, $field_name, $this->bundle);
      $formatter = $this->pluginManager->getInstance(array(
        'field_definition' => $instance,
        'view_mode' => $this->originalMode,
        // No need to prepare, defaults have been merged in setComponent().
        'prepare' => FALSE,
        'configuration' => $configuration
      ));
    }
    else {
      $formatter = NULL;
    }

    // Persist the formatter object.
    $this->plugins[$field_name] = $formatter;
    return $formatter;
  }

}
