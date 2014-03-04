<?php

/**
 * @file
 * Contains \Drupal\language\Form\NegotiationSessionForm.
 */

namespace Drupal\language\Form;

use Drupal\system\SystemConfigFormBase;

/**
 * Configure the session language negotiation method for this site.
 */
class NegotiationSessionForm extends SystemConfigFormBase {

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'language_negotiation_configure_session_form';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
    $config = $this->configFactory->get('language.negotiation');
    $form['language_negotiation_session_param'] = array(
      '#title' => t('Request/session parameter'),
      '#type' => 'textfield',
      '#default_value' => $config->get('session.parameter'),
      '#description' => t('Name of the request/session parameter used to determine the desired language.'),
    );

    $form_state['redirect'] = 'admin/config/regional/language/detection';

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->configFactory->get('language.settings')
      ->set('session.parameter', $form_state['values']['language_negotiation_session_param'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
