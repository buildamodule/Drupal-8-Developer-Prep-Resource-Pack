<?php

/**
 * @file
 * Contains \Drupal\filter\Plugin\Filter\FilterAutoP.
 */

namespace Drupal\filter\Plugin\Filter;

use Drupal\filter\Annotation\Filter;
use Drupal\Core\Annotation\Translation;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to conver line breaks to HTML.
 *
 * @Filter(
 *   id = "filter_autop",
 *   module = "filter",
 *   title = @Translation("Convert line breaks into HTML (i.e. <code>&lt;br&gt;</code> and <code>&lt;p&gt;</code>)"),
 *   type = FILTER_TYPE_MARKUP_LANGUAGE
 * )
 */
class FilterAutoP extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode, $cache, $cache_id) {
    return _filter_autop($text);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return t('Lines and paragraphs are automatically recognized. The &lt;br /&gt; line break, &lt;p&gt; paragraph and &lt;/p&gt; close paragraph tags are inserted automatically. If paragraphs are not recognized simply add a couple of blank lines.');
    }
    else {
      return t('Lines and paragraphs break automatically.');
    }
  }

}
