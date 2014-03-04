<?php

/**
 * @file
 * Contains \Drupal\file\Plugin\field\formatter\GenericFileFormatter.
 */

namespace Drupal\file\Plugin\field\formatter;

use Drupal\field\Annotation\FieldFormatter;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldInterface;

/**
 * Plugin implementation of the 'file_default' formatter.
 *
 * @FieldFormatter(
 *   id = "file_default",
 *   label = @Translation("Generic file"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class GenericFileFormatter extends FileFormatterBase {

  /**
   * Implements \Drupal\field\Plugin\Type\Formatter\FormatterInterface::viewElements().
   */
  public function viewElements(EntityInterface $entity, $langcode, FieldInterface $items) {
    $elements = array();

    foreach ($items as $delta => $item) {
      if ($item->display && $item->entity) {
        $elements[$delta] = array(
          '#theme' => 'file_link',
          '#file' => $item->entity,
          '#description' => $item->description,
        );
      }
    }

    return $elements;
  }

}
