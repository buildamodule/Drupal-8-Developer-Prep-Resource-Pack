<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\style\Rss.
 */

namespace Drupal\views\Plugin\views\style;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Default style plugin to render an RSS feed.
 *
 * @ingroup views_style_plugins
 *
 * @Plugin(
 *   id = "rss",
 *   title = @Translation("RSS Feed"),
 *   help = @Translation("Generates an RSS feed from a view."),
 *   theme = "views_view_rss",
 *   display_types = {"feed"}
 * )
 */
class Rss extends StylePluginBase {

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  public function attachTo($display_id, $path, $title) {
    $display = $this->view->displayHandlers->get($display_id);
    $url_options = array();
    $input = $this->view->getExposedInput();
    if ($input) {
      $url_options['query'] = $input;
    }
    $url_options['absolute'] = TRUE;

    $url = url($this->view->getUrl(NULL, $path), $url_options);
    if ($display->hasPath()) {
      if (empty($this->preview)) {
        drupal_add_feed($url, $title);
      }
    }
    else {
      if (empty($this->view->feed_icon)) {
        $this->view->feed_icon = '';
      }
      $feed_icon = array(
        '#theme' => 'feed_icon',
        '#url' => $url,
        '#title' => $title,
      );
      $this->view->feed_icon .= drupal_render($feed_icon);
      drupal_add_html_head_link(array(
        'rel' => 'alternate',
        'type' => 'application/rss+xml',
        'title' => $title,
        'href' => $url
      ));
    }
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['description'] = array('default' => '', 'translatable' => TRUE);

    return $options;
  }

  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['description'] = array(
      '#type' => 'textfield',
      '#title' => t('RSS description'),
      '#default_value' => $this->options['description'],
      '#description' => t('This will appear in the RSS feed itself.'),
      '#maxlength' => 1024,
    );
  }

  /**
   * Return an array of additional XHTML elements to add to the channel.
   *
   * @return
   *   An array that can be passed to format_xml_elements().
   */
  protected function getChannelElements() {
    return array();
  }

  /**
   * Get RSS feed description.
   *
   * @return string
   *   The string containing the description with the tokens replaced.
   */
  public function getDescription() {
    $description = $this->options['description'];

    // Allow substitutions from the first row.
    $description = $this->tokenizeValue($description, 0);

    return $description;
  }

  public function render() {
    if (empty($this->view->rowPlugin)) {
      debug('Drupal\views\Plugin\views\style\Rss: Missing row plugin');
      return;
    }
    $rows = '';

    // This will be filled in by the row plugin and is used later on in the
    // theming output.
    $this->namespaces = array('xmlns:dc' => 'http://purl.org/dc/elements/1.1/');

    // Fetch any additional elements for the channel and merge in their
    // namespaces.
    $this->channel_elements = $this->getChannelElements();
    foreach ($this->channel_elements as $element) {
      if (isset($element['namespace'])) {
        $this->namespaces = array_merge($this->namespaces, $element['namespace']);
      }
    }

    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows .= $this->view->rowPlugin->render($row);
    }

    $output = theme($this->themeFunctions(),
      array(
        'view' => $this->view,
        'options' => $this->options,
        'rows' => $rows
      ));
    unset($this->view->row_index);
    return $output;
  }

}
