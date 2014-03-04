<?php

/**
 * @file
 * Contains \Drupal\system\Form\LoggingForm.
 */

namespace Drupal\system\Form;

use Drupal\system\SystemConfigFormBase;

/**
 * Configure logging settings for this site.
 */
class LoggingForm extends SystemConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'system_logging_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $config = $this->configFactory->get('system.logging');
    $form['error_level'] = array(
      '#type' => 'radios',
      '#title' => t('Error messages to display'),
      '#default_value' => $config->get('error_level'),
      '#options' => array(
        ERROR_REPORTING_HIDE => t('None'),
        ERROR_REPORTING_DISPLAY_SOME => t('Errors and warnings'),
        ERROR_REPORTING_DISPLAY_ALL => t('All messages'),
        ERROR_REPORTING_DISPLAY_VERBOSE => t('All messages, with backtrace information'),
      ),
      '#description' => t('It is recommended that sites running on production environments do not display any errors.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->configFactory->get('system.logging')
      ->set('error_level', $form_state['values']['error_level'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
