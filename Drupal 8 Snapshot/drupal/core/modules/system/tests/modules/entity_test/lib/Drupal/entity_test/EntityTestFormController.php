<?php
/**
 * @file
 * Definition of Drupal\entity_test\EntityTestFormController.
 */

namespace Drupal\entity_test;

use Drupal\Core\Entity\EntityFormControllerNG;
use Drupal\Core\Language\Language;

/**
 * Form controller for the test entity edit forms.
 */
class EntityTestFormController extends EntityFormControllerNG {

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   */
  public function form(array $form, array &$form_state) {
    $form = parent::form($form, $form_state);
    $entity = $this->entity;

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#default_value' => $entity->name->value,
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#weight' => -10,
    );

    $form['user_id'] = array(
      '#type' => 'textfield',
      '#title' => 'UID',
      '#default_value' => $entity->user_id->target_id,
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#weight' => -10,
    );

    $form['langcode'] = array(
      '#title' => t('Language'),
      '#type' => 'language_select',
      '#default_value' => $entity->getUntranslated()->language()->id,
      '#languages' => Language::STATE_ALL,
    );

    // @todo: Is there a better way to check if an entity type is revisionable?
    $entity_info = $entity->entityInfo();
    if (!empty($entity_info['entity_keys']['revision']) && !$entity->isNew()) {
      $form['revision'] = array(
        '#type' => 'checkbox',
        '#title' => t('Create new revision'),
        '#default_value' => $entity->isNewRevision(),
      );
    }

    return $form;
  }

  /**
   * Overrides \Drupal\Core\Entity\EntityFormController::submit().
   */
  public function submit(array $form, array &$form_state) {
    // Build the entity object from the submitted values.
    $entity = parent::submit($form, $form_state);

    // Save as a new revision if requested to do so.
    if (!empty($form_state['values']['revision'])) {
      $entity->setNewRevision();
    }

    return $entity;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::save().
   */
  public function save(array $form, array &$form_state) {
    $entity = $this->entity;
    $is_new = $entity->isNew();
    $entity->save();

    if ($is_new) {
     $message = t('%entity_type @id has been created.', array('@id' => $entity->id(), '%entity_type' => $entity->entityType()));
    }
    else {
      $message = t('%entity_type @id has been updated.', array('@id' => $entity->id(), '%entity_type' => $entity->entityType()));
    }
    drupal_set_message($message);

    if ($entity->id()) {
      $form_state['redirect'] = $entity->entityType() . '/manage/' . $entity->id() . '/edit';
    }
    else {
      // Error on save.
      drupal_set_message(t('The entity could not be saved.'), 'error');
      $form_state['rebuild'] = TRUE;
    }
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::delete().
   */
  public function delete(array $form, array &$form_state) {
    $entity = $this->entity;
    $entity->delete();
    drupal_set_message(t('%entity_type @id has been deleted.', array('@id' => $entity->id(), '%entity_type' => $entity->entityType())));
    $form_state['redirect'] = '<front>';
  }
}
