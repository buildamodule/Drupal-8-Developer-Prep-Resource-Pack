<?php

/**
 * @file
 * Contains \Drupal\filter_test\Plugin\Filter\FilterTestReplace.
 */

namespace Drupal\filter_test\Plugin\Filter;

use Drupal\filter\Annotation\Filter;
use Drupal\Core\Annotation\Translation;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a test filter to replace all content.
 *
 * @Filter(
 *   id = "filter_test_replace",
 *   module = "filter_test",
 *   title = @Translation("Testing filter"),
 *   description = @Translation("Replaces all content with filter and text format information."),
 *   type = FILTER_TYPE_TRANSFORM_IRREVERSIBLE
 * )
 */
class FilterTestReplace extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode, $cache, $cache_id) {
    $text = array();
    $text[] = 'Filter: ' . $this->getLabel() . ' (' . $this->getPluginId() . ')';
    $text[] = 'Language: ' . $langcode;
    $text[] = 'Cache: ' . ($cache ? 'Enabled' : 'Disabled');
    if ($cache_id) {
      $text[] = 'Cache ID: ' . $cache_id;
    }
    return implode("<br />\n", $text);
  }

}
