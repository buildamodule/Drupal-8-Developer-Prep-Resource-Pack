<?php

/**
 * @file
 * Definition of Drupal\node\Plugin\views\field\Revision.
 */

namespace Drupal\node\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\node\Plugin\views\field\Node;
use Drupal\Component\Annotation\PluginID;

/**
 * A basic node_revision handler.
 *
 * @ingroup views_field_handlers
 *
 * @PluginID("node_revision")
 */
class Revision extends Node {

  /**
   * Overrides \Drupal\node\Plugin\views\field\Node::init().
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    if (!empty($this->options['link_to_node_revision'])) {
      $this->additional_fields['vid'] = 'vid';
      $this->additional_fields['nid'] = 'nid';
      if (module_exists('translation')) {
        $this->additional_fields['langcode'] = array('table' => 'node', 'field' => 'langcode');
      }
    }
  }
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['link_to_node_revision'] = array('default' => FALSE, 'bool' => TRUE);
    return $options;
  }

  /**
   * Provide link to revision option.
   */
  public function buildOptionsForm(&$form, &$form_state) {
    $form['link_to_node_revision'] = array(
      '#title' => t('Link this field to its content revision'),
      '#description' => t('This will override any other link you have set.'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->options['link_to_node_revision']),
    );
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Render whatever the data is as a link to the node.
   *
   * Data should be made XSS safe prior to calling this function.
   */
  protected function renderLink($data, ResultRow $values) {
    if (!empty($this->options['link_to_node_revision']) && $data !== NULL && $data !== '') {
      $this->options['alter']['make_link'] = TRUE;
      $nid = $this->getValue($values, 'nid');
      $vid = $this->getValue($values, 'vid');
      $this->options['alter']['path'] = "node/" . $nid . '/revisions/' . $vid . '/view';
      if (module_exists('translation')) {
        $langcode = $this->getValue($values, 'langcode');
        $languages = language_list();
        if (isset($languages[$langcode])) {
          $this->options['alter']['langcode'] = $languages[$langcode];
        }
      }
    }
    else {
      return parent::renderLink($data, $values);
    }
    return $data;
  }

}
