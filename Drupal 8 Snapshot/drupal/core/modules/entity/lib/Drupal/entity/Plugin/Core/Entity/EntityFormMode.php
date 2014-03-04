<?php

/**
 * @file
 * Contains \Drupal\entity\Plugin\Core\Entity\EntityFormMode.
 */

namespace Drupal\entity\Plugin\Core\Entity;

use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Annotation\Translation;
use Drupal\entity\EntityDisplayModeBase;
use Drupal\entity\EntityFormModeInterface;

/**
 * Defines the form mode configuration entity class.
 *
 * Form modes allow entity forms to be displayed differently depending on the
 * context. For instance, the user entity form can be displayed with a set of
 * fields on the 'profile' page (user edit page) and with a different set of
 * fields (or settings) on the user registration page. Modules taking part in
 * the display of the entity form (notably the Field API) can adjust their
 * behavior depending on the requested form mode. An additional 'default' form
 * mode is available for all entity types. For each available form mode,
 * administrators can configure whether it should use its own set of field
 * display settings, or just replicate the settings of the 'default' form mode,
 * thus reducing the amount of form display configurations to keep track of.
 *
 * @see entity_get_form_modes()
 * @see hook_entity_form_mode_info_alter()
 *
 * @EntityType(
 *   id = "form_mode",
 *   label = @Translation("Form mode"),
 *   module = "entity",
 *   controllers = {
 *     "list" = "Drupal\entity\EntityFormModeListController",
 *     "access" = "Drupal\entity\EntityDisplayModeAccessController",
 *     "form" = {
 *       "add" = "Drupal\entity\Form\EntityFormModeAddForm",
 *       "edit" = "Drupal\entity\Form\EntityDisplayModeEditForm",
 *       "delete" = "Drupal\entity\Form\EntityDisplayModeDeleteForm"
 *     },
 *     "storage" = "Drupal\entity\EntityDisplayModeStorageController"
 *   },
 *   config_prefix = "entity.form_mode",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class EntityFormMode extends EntityDisplayModeBase implements EntityFormModeInterface {

  /**
   * {@inheritdoc}
   */
  public function uri() {
    return array(
      'path' => 'admin/structure/display-modes/form/manage/' . $this->id(),
      'options' => array(
        'entity_type' => $this->entityType,
        'entity' => $this,
      ),
    );
  }

}
