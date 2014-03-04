<?php

/**
 * @file
 * Definition of views_handler_argument_field_list.
 */

namespace Drupal\field\Plugin\views\argument;

use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\argument\Numeric;
use Drupal\Component\Annotation\PluginID;

/**
 * Argument handler for list field to show the human readable name in the
 * summary.
 *
 * @ingroup views_argument_handlers
 *
 * @PluginID("field_list")
 */
class FieldList extends Numeric {

  /**
   * Stores the allowed values of this field.
   *
   * @var array
   */
  var $allowed_values = NULL;

  /**
   * Overrides \Drupal\views\Plugin\views\argument\ArgumentPluginBase::init().
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $field = field_info_field($this->definition['field_name']);
    $this->allowed_values = options_allowed_values($field);
  }

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['summary']['contains']['human'] = array('default' => FALSE, 'bool' => TRUE);

    return $options;
  }

  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['summary']['human'] = array(
      '#title' => t('Display list value as human readable'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['summary']['human'],
      '#states' => array(
        'visible' => array(
          ':input[name="options[default_action]"]' => array('value' => 'summary'),
        ),
      ),
    );
  }

  public function summaryName($data) {
    $value = $data->{$this->name_alias};
    // If the list element has a human readable name show it,
    if (isset($this->allowed_values[$value]) && !empty($this->options['summary']['human'])) {
      return field_filter_xss($this->allowed_values[$value]);
    }
    // else fallback to the key.
    else {
      return check_plain($value);
    }
  }

}
