<?php

/**
 * @file
 * Definition of Drupal\node\NodeTranslationController.
 */

namespace Drupal\node;

use Drupal\Core\Entity\EntityInterface;
use Drupal\content_translation\ContentTranslationControllerNG;

/**
 * Defines the translation controller class for nodes.
 */
class NodeTranslationController extends ContentTranslationControllerNG {

  /**
   * Overrides ContentTranslationController::entityFormAlter().
   */
  public function entityFormAlter(array &$form, array &$form_state, EntityInterface $entity) {
    parent::entityFormAlter($form, $form_state, $entity);

    // Move the translation fieldset to a vertical tab.
    if (isset($form['content_translation'])) {
      $form['content_translation'] += array(
        '#group' => 'additional_settings',
        '#weight' => 100,
        '#attributes' => array(
          'class' => array('node-translation-options'),
        ),
      );

      // We do not need to show these values on node forms: they inherit the
      // basic node property values.
      $form['content_translation']['status']['#access'] = FALSE;
      $form['content_translation']['name']['#access'] = FALSE;
      $form['content_translation']['created']['#access'] = FALSE;
    }
  }

  /**
   * Overrides ContentTranslationController::entityFormTitle().
   */
  protected function entityFormTitle(EntityInterface $entity) {
    $type_name = node_get_type_label($entity);
    return t('<em>Edit @type</em> @title', array('@type' => $type_name, '@title' => $entity->label()));
  }

  /**
   * Overrides ContentTranslationController::entityFormEntityBuild().
   */
  public function entityFormEntityBuild($entity_type, EntityInterface $entity, array $form, array &$form_state) {
    if (isset($form_state['values']['content_translation'])) {
      $form_controller = content_translation_form_controller($form_state);
      $translation = &$form_state['values']['content_translation'];
      $translation['status'] = $form_controller->getEntity()->status;
      $translation['name'] = $form_state['values']['name'];
      $translation['created'] = $form_state['values']['date'];
    }
    parent::entityFormEntityBuild($entity_type, $entity, $form, $form_state);
  }

}
