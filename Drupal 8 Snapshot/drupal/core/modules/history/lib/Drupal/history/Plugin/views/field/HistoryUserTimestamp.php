<?php

/**
 * @file
 * Contains \Drupal\history\Plugin\views\field\HistoryUserTimestamp.
 */

namespace Drupal\history\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\node\Plugin\views\field\Node;
use Drupal\Component\Annotation\PluginID;

/**
 * Field handler to display the marker for new content.
 *
 * The handler is named history_user, because of compatibility reasons, the
 * table is history.
 *
 * @ingroup views_field_handlers
 *
 * @PluginID("history_user_timestamp")
 */
class HistoryUserTimestamp extends Node {

  /**
   * Overrides \Drupal\node\Plugin\views\field\Node::init().
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    global $user;
    if ($user->isAuthenticated()) {
      $this->additional_fields['created'] = array('table' => 'node_field_data', 'field' => 'created');
      $this->additional_fields['changed'] = array('table' => 'node_field_data', 'field' => 'changed');
      if (module_exists('comment') && !empty($this->options['comments'])) {
        $this->additional_fields['last_comment'] = array('table' => 'node_comment_statistics', 'field' => 'last_comment_timestamp');
      }
    }
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['comments'] = array('default' => FALSE, 'bool' => TRUE);

    return $options;
  }

  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);
    if (module_exists('comment')) {
      $form['comments'] = array(
        '#type' => 'checkbox',
        '#title' => t('Check for new comments as well'),
        '#default_value' => !empty($this->options['comments']),
      );
    }
  }

  public function query() {
    // Only add ourselves to the query if logged in.
    global $user;
    if ($user->isAnonymous()) {
      return;
    }
    parent::query();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Let's default to 'read' state.
    // This code shadows node_mark, but it reads from the db directly and
    // we already have that info.
    $mark = MARK_READ;
    global $user;
    if ($user->isAuthenticated()) {
      $last_read = $this->getValue($values);
      $changed = $this->getValue($values, 'changed');

      $last_comment = module_exists('comment') && !empty($this->options['comments']) ?  $this->getValue($values, 'last_comment') : 0;

      if (!$last_read && $changed > HISTORY_READ_LIMIT) {
        $mark = MARK_NEW;
      }
      elseif ($changed > $last_read && $changed > HISTORY_READ_LIMIT) {
        $mark = MARK_UPDATED;
      }
      elseif ($last_comment > $last_read && $last_comment > HISTORY_READ_LIMIT) {
        $mark = MARK_UPDATED;
      }
      $build = array(
        '#theme' => 'mark',
        '#status' => $mark,
      );
      return $this->renderLink(drupal_render($build), $values);
    }
  }

}
