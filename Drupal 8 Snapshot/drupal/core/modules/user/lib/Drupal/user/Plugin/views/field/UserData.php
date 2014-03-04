<?php

/**
 * @file
 * Contains \Drupal\user\Plugin\views\field\UserData.
 */

namespace Drupal\user\Plugin\views\field;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\user\UserDataInterface;
use Drupal\Component\Annotation\PluginID;

/**
 * Provides access to the user data service.
 *
 * @ingroup views_field_handlers
 *
 * @see \Drupal\user\UserDataInterface
 *
 * @PluginID("user_data")
 */
class UserData extends FieldPluginBase {

  /**
   * Provides the user data service object.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Overrides \Drupal\views\Plugin\views\field\FieldPluginBase::init().
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->userData = drupal_container()->get('user.data');
  }

  /**
   * Overrides \Drupal\views\Plugin\views\field\FieldPluginBase::defineOptions().
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['data_module'] = array('default' => '');
    $options['data_name'] = array('default' => '');

    return $options;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\field\FieldPluginBase::defineOptions().
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['data_module'] = array(
      '#title' => t('Module name'),
      '#type' => 'select',
      '#description' => t('The module which sets this user data.'),
      '#default_value' => $this->options['data_module'],
      '#options' => system_get_module_info('name'),
    );

    $form['data_name'] = array(
      '#title' => t('Name'),
      '#type' => 'textfield',
      '#description' => t('The name of the data key.'),
      '#default_value' => $this->options['data_name'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $uid = $this->getValue($values);
    $data = $this->userData->get($this->options['data_module'], $uid, $this->options['data_name']);

    // Don't sanitize if no value was found.
    if (isset($data)) {
      return $this->sanitizeValue($data);
    }
  }

}
