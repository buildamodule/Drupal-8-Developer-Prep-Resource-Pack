<?php

/**
 * @file
 * Contains \Drupal\aggregator\Plugin\views\field\Xss.
 */

namespace Drupal\aggregator\Plugin\views\field;

use Drupal\views\Plugin\views\field\Xss as XssBase;
use Drupal\Component\Annotation\PluginID;

/**
 * Filters htmls tags from item.
 *
 * @ingroup views_field_handlers
 *
 * @PluginID("aggregator_xss")
 */
class Xss extends XssBase {

  /**
   * {@inheritdoc}
   */
  public function sanitizeValue($value, $type = NULL) {
    return aggregator_filter_xss($value);
  }

}
