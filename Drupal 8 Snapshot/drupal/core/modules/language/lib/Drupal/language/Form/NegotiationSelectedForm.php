<?php

/**
 * @file
 * Contains \Drupal\language\Form\NegotiationSelectedForm.
 */

namespace Drupal\language\Form;

use Drupal\Core\Language\Language;
use Drupal\system\SystemConfigFormBase;

/**
 * Configure the selected language negotiation method for this site.
 */
class NegotiationSelectedForm extends SystemConfigFormBase {

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'language_negotiation_configure_selected_form';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
    $config = $this->configFactory->get('language.negotiation');
    $form['selected_langcode'] = array(
      '#type' => 'language_select',
      '#title' => t('Language'),
      '#languages' => Language::STATE_CONFIGURABLE | Language::STATE_SITE_DEFAULT,
      '#default_value' => $config->get('selected_langcode'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->configFactory->get('language.negotiation')
      ->set('selected_langcode', $form_state['values']['selected_langcode'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
