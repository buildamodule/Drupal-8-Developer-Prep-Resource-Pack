<?php

/**
 * @file
 * Contains \Drupal\views\Plugin\views\field\Links.
 */

namespace Drupal\views\Plugin\views\field;

/**
 * A abstract handler which provides a collection of links.
 *
 * @ingroup views_field_handlers
 */
abstract class Links extends FieldPluginBase {

  /**
   * Overrides \Drupal\views\Plugin\views\field\FieldPluginBase::defineOptions().
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['fields'] = array('default' => array());
    $options['destination'] = array('default' => TRUE, 'bool' => TRUE);

    return $options;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\field\FieldPluginBase::defineOptions().
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);
    // Only show fields that precede this one.
    $field_options = $this->getPreviousFieldLabels();
    $form['fields'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Fields'),
      '#description' => t('Fields to be included as links.'),
      '#options' => $field_options,
      '#default_value' => $this->options['fields'],
    );
    $form['destination'] = array(
      '#type' => 'checkbox',
      '#title' => t('Include destination'),
      '#description' => t('Include a "destination" parameter in the link to return the user to the original view upon completing the link action.'),
      '#default_value' => $this->options['destination'],
    );
  }

  /**
   * Gets the list of links used by this field.
   *
   * @return array
   *   The links which are used by the render function.
   */
  protected function getLinks() {
    $links = array();
    foreach ($this->options['fields'] as $field) {
      if (empty($this->view->field[$field]->last_render_text)) {
        continue;
      }
      $title = $this->view->field[$field]->last_render_text;
      $path = '';
      if (!empty($this->view->field[$field]->options['alter']['path'])) {
        $path = $this->view->field[$field]->options['alter']['path'];
      }
      // Make sure that tokens are replaced for this paths as well.
      $tokens = $this->getRenderTokens(array());
      $path = strip_tags(decode_entities(strtr($path, $tokens)));

      $links[$field] = array(
        'href' => $path,
        'title' => $title,
      );
      if (!empty($this->options['destination'])) {
        $links[$field]['query'] = drupal_get_destination();
      }
    }

    return $links;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\field\FieldPluginBase::query().
   */
  public function query() {
  }

}
