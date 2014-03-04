<?php

/**
 * @file
 * Contains \Drupal\comment\Plugin\views\field\LinkEdit.
 */

namespace Drupal\comment\Plugin\views\field;

use Drupal\Component\Annotation\PluginID;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to edit a comment.
 *
 * @ingroup views_field_handlers
 *
 * @PluginID("comment_link_edit")
 */
class LinkEdit extends Link {

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['destination'] = array('default' => FALSE, 'bool' => TRUE);

    return $options;
  }

  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['destination'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use destination'),
      '#description' => t('Add destination to the link'),
      '#default_value' => $this->options['destination'],
    );
  }

  protected function renderLink($data, ResultRow $values) {
    parent::renderLink($data, $values);
    // Ensure user has access to edit this comment.
    $comment = $this->getValue($values);
    if (!$comment->access('update')) {
      return;
    }

    $text = !empty($this->options['text']) ? $this->options['text'] : t('edit');
    unset($this->options['alter']['fragment']);

    if (!empty($this->options['destination'])) {
      $this->options['alter']['query'] = drupal_get_destination();
    }

    $this->options['alter']['path'] = "comment/" . $comment->id() . "/edit";

    return $text;
  }

}
