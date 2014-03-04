<?php

/**
 * @file
 * Contains \Drupal\custom_block\CustomBlockTypeFormController.
 */

namespace Drupal\custom_block;

use Drupal\Core\Entity\EntityFormController;

/**
 * Base form controller for category edit forms.
 */
class CustomBlockTypeFormController extends EntityFormController {

  /**
   * Overrides \Drupal\Core\Entity\EntityFormController::form().
   */
  public function form(array $form, array &$form_state) {
    $form = parent::form($form, $form_state);

    $block_type = $this->entity;

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#maxlength' => 255,
      '#default_value' => $block_type->label(),
      '#description' => t("Provide a label for this block type to help identify it in the administration pages."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $block_type->id(),
      '#machine_name' => array(
        'exists' => 'custom_block_type_load',
      ),
      '#disabled' => !$block_type->isNew(),
    );

    $form['description'] = array(
      '#type' => 'textarea',
      '#default_value' => $block_type->description,
      '#description' => t('Enter a description for this block type.'),
      '#title' => t('Description'),
    );

    $form['revision'] = array(
      '#type' => 'checkbox',
      '#title' => t('Create new revision'),
      '#default_value' => $block_type->revision,
      '#description' => t('Create a new revision by default for this block type.')
    );

    if (module_exists('content_translation')) {
      $form['language'] = array(
        '#type' => 'details',
        '#title' => t('Language settings'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#group' => 'additional_settings',
      );

      $language_configuration = language_get_default_configuration('custom_block', $block_type->id());
      $form['language']['language_configuration'] = array(
        '#type' => 'language_configuration',
        '#entity_information' => array(
          'entity_type' => 'custom_block',
          'bundle' => $block_type->id(),
        ),
        '#default_value' => $language_configuration,
      );

      $form['#submit'][] = 'language_configuration_element_submit';
    }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );

    return $form;
  }

  /**
   * Overrides \Drupal\Core\Entity\EntityFormController::save().
   */
  public function save(array $form, array &$form_state) {
    $block_type = $this->entity;
    $status = $block_type->save();

    $uri = $block_type->uri();
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Custom block type %label has been updated.', array('%label' => $block_type->label())));
      watchdog('custom_block', 'Custom block type %label has been updated.', array('%label' => $block_type->label()), WATCHDOG_NOTICE, l(t('Edit'), $uri['path'] . '/edit'));
    }
    else {
      drupal_set_message(t('Custom block type %label has been added.', array('%label' => $block_type->label())));
      watchdog('custom_block', 'Custom block type %label has been added.', array('%label' => $block_type->label()), WATCHDOG_NOTICE, l(t('Edit'), $uri['path'] . '/edit'));
    }

    $form_state['redirect'] = 'admin/structure/custom-blocks/types';
  }

  /**
   * Overrides \Drupal\Core\Entity\EntityFormController::delete().
   */
  public function delete(array $form, array &$form_state) {
    $block_type = $this->entity;
    $form_state['redirect'] = 'admin/structure/custom-blocks/manage/' . $block_type->id() . '/delete';
  }

}
