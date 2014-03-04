<?php

/**
 * @file
 * Contains \Drupal\aggregator\Plugin\views\row\Rss.
 */

namespace Drupal\aggregator\Plugin\views\row;

use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a row plugin which loads an aggregator item and renders as RSS.
 *
 * @Plugin(
 *   id = "aggregator_rss",
 *   module = "aggregator",
 *   theme = "views_view_row_rss",
 *   title = @Translation("Aggregator item"),
 *   help = @Translation("Display the aggregator item using the data from the original source."),
 *   base = {"aggregator_item"},
 *   display_types = {"feed"}
 * )
 */
class Rss extends RowPluginBase {

  /**
   * The table the aggregator item is using for storage.
   *
   * @var string
   */
  public $base_table = 'aggregator_item';

  /**
   * The actual field which is used to identify a aggregator item.
   *
   * @var string
   */
  public $base_field = 'iid';

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['item_length'] = array('default' => 'default');

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, &$form_state) {
    $form['item_length'] = array(
      '#type' => 'select',
      '#title' => t('Display type'),
      '#options' => array(
        'fulltext' => t('Full text'),
        'teaser' => t('Title plus teaser'),
        'title' => t('Title only'),
        'default' => t('Use default RSS settings'),
      ),
      '#default_value' => $this->options['item_length'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    $entity = $row->_entity;

    $item = new \stdClass();
    foreach ($entity->getProperties() as $name => $value) {
      // views_view_row_rss takes care about the escaping.
      $item->{$name} = $value->value;
    }

    $item->elements = array(
      array(
        'key' => 'pubDate',
        // views_view_row_rss takes care about the escaping.
        'value' => gmdate('r', $entity->timestamp->value),
      ),
      array(
        'key' => 'dc:creator',
        // views_view_row_rss takes care about the escaping.
        'value' => $entity->author->value,
      ),
      array(
        'key' => 'guid',
        // views_view_row_rss takes care about the escaping.
        'value' => $entity->guid->value,
        'attributes' => array('isPermaLink' => 'false'),
      ),
    );

    $build = array(
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#row' => $item,
    );
    return drupal_render($build);
  }

}
