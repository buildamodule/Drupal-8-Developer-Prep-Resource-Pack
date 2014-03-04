<?php

/**
 * @file
 * Contains \Drupal\comment\Plugin\views\row\CommentRow.
 */

namespace Drupal\comment\Plugin\views\row;

use Drupal\views\Plugin\views\row\EntityRow;

/**
 * Plugin which performs a comment_view on the resulting object.
 */
class CommentRow extends EntityRow {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['links'] = array('default' => TRUE);
    $options['view_mode']['default'] = 'full';
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['links'] = array(
      '#type' => 'checkbox',
      '#title' => t('Display links'),
      '#default_value' => $this->options['links'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    $entity_id = $row->{$this->field_alias};
    $build = $this->build[$entity_id];
    if (!$this->options['links']) {
      unset($build['links']);
    }
    return $build;
  }

}
