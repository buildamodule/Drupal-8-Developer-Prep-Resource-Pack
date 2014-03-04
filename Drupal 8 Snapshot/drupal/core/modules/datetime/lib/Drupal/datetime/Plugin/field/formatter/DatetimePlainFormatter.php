<?php

/**
 * @file
 * Contains \Drupal\datetime\Plugin\field\formatter\DateTimePlainFormatter.
 */

namespace Drupal\datetime\Plugin\field\formatter;

use Drupal\field\Annotation\FieldFormatter;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Plugin implementation of the 'datetime_plain' formatter.
 *
 * @FieldFormatter(
 *   id = "datetime_plain",
 *   label = @Translation("Plain"),
 *   field_types = {
 *     "datetime"
 *   }
 *)
 */
class DateTimePlainFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(EntityInterface $entity, $langcode, FieldInterface $items) {

    $elements = array();

    foreach ($items as $delta => $item) {

      $output = '';
      if (!empty($item->date)) {
        // The date was created and verified during field_load(), so it is safe
        // to use without further inspection.
        $date = $item->date;
        $date->setTimeZone(timezone_open(drupal_get_user_timezone()));
        $format = DATETIME_DATETIME_STORAGE_FORMAT;
        if ($this->getFieldSetting('datetime_type') == 'date') {
          // A date without time will pick up the current time, use the default.
          datetime_date_default_time($date);
          $format = DATETIME_DATE_STORAGE_FORMAT;
        }
        $output = $date->format($format);
      }
      $elements[$delta] = array('#markup' => $output);
    }

    return $elements;
  }

}
