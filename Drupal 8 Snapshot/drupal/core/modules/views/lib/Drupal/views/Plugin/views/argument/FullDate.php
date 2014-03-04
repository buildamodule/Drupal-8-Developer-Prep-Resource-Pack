<?php

/**
 * @file
 * Contains \Drupal\views\Plugin\views\argument\FullDate.
 */

namespace Drupal\views\Plugin\views\argument;

use Drupal\Component\Annotation\PluginID;

/**
 * Argument handler for a full date (CCYYMMDD)
 *
 * @PluginID("date_fulldate")
 */
class FullDate extends Date {

  /**
   * {@inheritdoc}
   */
  protected $format = 'F j, Y';

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'Ymd';

  /**
   * Provide a link to the next level of the view
   */
  public function summaryName($data) {
    $created = $data->{$this->name_alias};
    return format_date(strtotime($created . " 00:00:00 UTC"), 'custom', $this->format, 'UTC');
  }

  /**
   * Provide a link to the next level of the view
   */
  function title() {
    return format_date(strtotime($this->argument . " 00:00:00 UTC"), 'custom', $this->format, 'UTC');
  }

}
