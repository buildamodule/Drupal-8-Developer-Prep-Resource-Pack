<?php

/**
 * @file
 * Contains \Drupal\options\Plugin\field\formatter\OptionsKeyFormatter.
 */

namespace Drupal\options\Plugin\field\formatter;

use Drupal\field\Annotation\FieldFormatter;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldInterface;

/**
 * Plugin implementation of the 'list_key' formatter.
 *
 * @FieldFormatter(
 *   id = "list_key",
 *   label = @Translation("Key"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_text",
 *     "list_boolean"
 *   }
 * )
 */
class OptionsKeyFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(EntityInterface $entity, $langcode, FieldInterface $items) {
    $elements = array();

    foreach ($items as $delta => $item) {
      $elements[$delta] = array('#markup' => field_filter_xss($item->value));
    }

    return $elements;
  }

}
