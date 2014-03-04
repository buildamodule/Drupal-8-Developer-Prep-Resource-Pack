<?php

/**
 * @file
 * Contains Drupal\language\Plugin\views\field\LanguageField.
 */

namespace Drupal\language\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Component\Annotation\PluginID;
use Drupal\views\ResultRow;

/**
 * Defines a field handler to translate a language into its readable form.
 *
 * @ingroup views_field_handlers
 *
 * @PluginID("language")
 */
class LanguageField extends FieldPluginBase {

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['native_language'] = array('default' => FALSE, 'bool' => TRUE);

    return $options;
  }

  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['native_language'] = array(
      '#title' => t('Native language'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['native_language'],
      '#description' => t('If enabled, the native name of the language will be displayed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // @todo: Drupal Core dropped native language until config translation is
    // ready, see http://drupal.org/node/1616594.
    $value = $this->getValue($values);
    $language = language_load($value);
    return $language ? $language->name : '';
  }

}
