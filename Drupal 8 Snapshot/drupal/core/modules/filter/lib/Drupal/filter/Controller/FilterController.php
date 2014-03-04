<?php

/**
 * @file
 * Contains \Drupal\filter\Controller\FilterController.
 */

namespace Drupal\filter\Controller;

use Drupal\filter\FilterFormatInterface;

/**
 * Controller routines for filter routes.
 */
class FilterController {

  /**
   * Displays a page with long filter tips.
   *
   * @param \Drupal\filter\FilterFormatInterface|null $format
   *   A filter format, or NULL to show tips for all formats. Defaults to NULL.
   *
   * @return array
   *   A renderable array.
   *
   * @see theme_filter_tips()
   */
  function filterTips(FilterFormatInterface $filter_format = NULL) {
    $tips = $filter_format ? $filter_format->format : -1;

    $build = array(
      '#theme' => 'filter_tips',
      '#long' => TRUE,
      '#tips' => _filter_tips($tips, TRUE),
    );

    return $build;
  }

}
