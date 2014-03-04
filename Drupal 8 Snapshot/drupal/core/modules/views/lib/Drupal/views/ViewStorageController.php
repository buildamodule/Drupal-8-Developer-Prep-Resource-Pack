<?php

/**
 * @file
 * Definition of Drupal\views\ViewStorageController.
 */

namespace Drupal\views;

use Drupal\Core\Config\Entity\ConfigStorageController;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the storage controller class for View entities.
 */
class ViewStorageController extends ConfigStorageController {

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    $entities = parent::loadMultiple($ids);

    // Only return views for enabled modules.
    return array_filter($entities, function ($entity) {
      if (\Drupal::moduleHandler()->moduleExists($entity->get('module'))) {
        return TRUE;
      }
      return FALSE;
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function attachLoad(&$queried_entities, $revision_id = FALSE) {
    foreach ($queried_entities as $id => $entity) {
      $entity->mergeDefaultDisplaysOptions();
    }

    parent::attachLoad($queried_entities, $revision_id);
  }


}
