<?php

/**
 * @file
 * Contains \Drupal\forum\ForumSettingsForm.
 */

namespace Drupal\forum;

use Drupal\system\SystemConfigFormBase;

/**
 * Configure forum settings for this site.
 */
class ForumSettingsForm extends SystemConfigFormBase {

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'forum_admin_settings';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
    $config = $this->configFactory->get('forum.settings');

    $number = drupal_map_assoc(array(5, 10, 15, 20, 25, 30, 35, 40, 50, 60, 80, 100, 150, 200, 250, 300, 350, 400, 500));
    $form['forum_hot_topic'] = array(
      '#type' => 'select',
      '#title' => t('Hot topic threshold'),
      '#default_value' => $config->get('topics.hot_threshold'),
      '#options' => $number,
      '#description' => t('The number of replies a topic must have to be considered "hot".'),
    );
    $number = drupal_map_assoc(array(10, 25, 50, 75, 100));
    $form['forum_per_page'] = array(
      '#type' => 'select',
      '#title' => t('Topics per page'),
      '#default_value' => $config->get('topics.page_limit'),
      '#options' => $number,
      '#description' => t('Default number of forum topics displayed per page.'),
    );
    $forder = array(
      1 => t('Date - newest first'),
      2 => t('Date - oldest first'),
      3 => t('Posts - most active first'),
      4 => t('Posts - least active first')
    );
    $form['forum_order'] = array(
      '#type' => 'radios',
      '#title' => t('Default order'),
      '#default_value' => $config->get('topics.order'),
      '#options' => $forder,
      '#description' => t('Default display order for topics.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->configFactory->get('forum.settings')
      ->set('topics.hot_threshold', $form_state['values']['forum_hot_topic'])
      ->set('topics.page_limit', $form_state['values']['forum_per_page'])
      ->set('topics.order', $form_state['values']['forum_order'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
