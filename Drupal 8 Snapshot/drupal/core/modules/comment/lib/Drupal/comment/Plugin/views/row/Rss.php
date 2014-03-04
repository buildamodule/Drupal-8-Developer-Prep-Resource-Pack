<?php

/**
 * @file
 * Definition of Drupal\comment\Plugin\views\row\Rss.
 */

namespace Drupal\comment\Plugin\views\row;

use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Plugin which formats the comments as RSS items.
 *
 * @Plugin(
 *   id = "comment_rss",
 *   module = "comment",
 *   title = @Translation("Comment"),
 *   help = @Translation("Display the comment as RSS."),
 *   theme = "views_view_row_rss",
 *   base = {"comment"},
 *   display_types = {"feed"}
 * )
 */
class Rss extends RowPluginBase {

   var $base_table = 'comment';
   var $base_field = 'cid';

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['item_length'] = array('default' => 'default');
    $options['links'] = array('default' => FALSE, 'bool' => TRUE);

    return $options;
  }

  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['item_length'] = array(
      '#type' => 'select',
      '#title' => t('Display type'),
      '#options' => $this->options_form_summary_options(),
      '#default_value' => $this->options['item_length'],
    );
    $form['links'] = array(
      '#type' => 'checkbox',
      '#title' => t('Display links'),
      '#default_value' => $this->options['links'],
    );
  }

  public function preRender($result) {
    $cids = array();
    $nids = array();

    foreach ($result as $row) {
      $cids[] = $row->cid;
    }

    $this->comments = comment_load_multiple($cids);
    foreach ($this->comments as $comment) {
      $comment->depth = count(explode('.', $comment->thread->value)) - 1;
    }

  }

  /**
   * Return the main options, which are shown in the summary title
   *
   * @see views_plugin_row_node_rss::options_form_summary_options()
   * @todo: Maybe provide a views_plugin_row_rss_entity and reuse this method
   * in views_plugin_row_comment|node_rss.inc
   */
  function options_form_summary_options() {
    $view_modes = entity_get_view_modes('node');
    $options = array();
    foreach ($view_modes as $mode => $settings) {
      $options[$mode] = $settings['label'];
    }
    $options['title'] = t('Title only');
    $options['default'] = t('Use site default RSS settings');
    return $options;
  }

  public function render($row) {
    global $base_url;

    $cid = $row->{$this->field_alias};
    if (!is_numeric($cid)) {
      return;
    }

    $item_length = $this->options['item_length'];
    if ($item_length == 'default') {
      $item_length = \Drupal::config('system.rss')->get('items.view_mode');
    }

    // Load the specified comment and its associated node:
    $comment = $this->comments[$cid];
    if (empty($comment)) {
      return;
    }

    $item_text = '';

    $uri = $comment->uri();
    $comment->link = url($uri['path'], $uri['options'] + array('absolute' => TRUE));
    $comment->rss_namespaces = array();
    $comment->rss_elements = array(
      array(
        'key' => 'pubDate',
        'value' => gmdate('r', $comment->created->value),
      ),
      array(
        'key' => 'dc:creator',
        'value' => $comment->name->value,
      ),
      array(
        'key' => 'guid',
        'value' => 'comment ' . $comment->id() . ' at ' . $base_url,
        'attributes' => array('isPermaLink' => 'false'),
      ),
    );

    // The comment gets built and modules add to or modify
    // $comment->rss_elements and $comment->rss_namespaces.
    $build = comment_view($comment, 'rss');
    unset($build['#theme']);

    if (!empty($comment->rss_namespaces)) {
      $this->view->style_plugin->namespaces = array_merge($this->view->style_plugin->namespaces, $comment->rss_namespaces);
    }

    // Hide the links if desired.
    if (!$this->options['links']) {
      hide($build['links']);
    }

    if ($item_length != 'title') {
      // We render comment contents and force links to be last.
      $build['links']['#weight'] = 1000;
      $item_text .= drupal_render($build);
    }

    $item = new \stdClass();
    $item->description = $item_text;
    $item->title = $comment->label();
    $item->link = $comment->link;
    $item->elements = $comment->rss_elements;
    $item->cid = $comment->id();

    $build = array(
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#row' => $item,
    );
    return drupal_render($build);
  }

}
