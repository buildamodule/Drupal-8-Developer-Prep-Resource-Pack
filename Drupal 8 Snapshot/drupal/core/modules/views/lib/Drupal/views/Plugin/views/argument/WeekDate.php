<?php

/**
 * @file
 * Contains \Drupal\views\Plugin\views\argument\WeekDate.
 */

namespace Drupal\views\Plugin\views\argument;

use Drupal\Component\Annotation\PluginID;

/**
 * Argument handler for a week.
 *
 * @PluginID("date_week")
 */
class WeekDate extends Date {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'W';

  /**
   * Provide a link to the next level of the view
   */
  public function summaryName($data) {
    $created = $data->{$this->name_alias};
    return t('Week @week', array('@week' => $created));
  }

}
