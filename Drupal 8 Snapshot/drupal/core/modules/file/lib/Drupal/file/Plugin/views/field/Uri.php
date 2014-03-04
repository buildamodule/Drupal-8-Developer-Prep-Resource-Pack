<?php

/**
 * @file
 * Definition of Drupal\file\Plugin\views\field\Uri.
 */

namespace Drupal\file\Plugin\views\field;

use Drupal\Component\Annotation\PluginID;
use Drupal\views\ResultRow;

/**
 * Field handler to add rendering file paths as file URLs instead of as internal file URIs.
 *
 * @PluginID("file_uri")
 */
class Uri extends File {

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['file_download_path'] = array('default' => FALSE, 'bool' => TRUE);
    return $options;
  }

  public function buildOptionsForm(&$form, &$form_state) {
    $form['file_download_path'] = array(
      '#title' => t('Display download path instead of file storage URI'),
      '#description' => t('This will provide the full download URL rather than the internal filestream address.'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->options['file_download_path']),
    );
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $data = $values->{$this->field_alias};
    if (!empty($this->options['file_download_path']) && $data !== NULL && $data !== '') {
      $data = file_create_url($data);
    }
    return $this->renderLink($data, $values);
  }

}
