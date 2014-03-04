<?php

/**
 * @file
 * Contains \Drupal\number\Plugin\field\formatter\DefaultNumberFormatter.
 */

namespace Drupal\number\Plugin\field\formatter;

use Drupal\field\Annotation\FieldFormatter;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldInterface;

/**
 * Parent plugin for decimal and integer formatters
 */
abstract class DefaultNumberFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, array &$form_state) {
    $options = array(
      ''  => t('- None -'),
      '.' => t('Decimal point'),
      ',' => t('Comma'),
      ' ' => t('Space'),
      chr(8201) => t('Thin space'),
      "'" => t('Apostrophe'),
    );
    $elements['thousand_separator'] = array(
      '#type' => 'select',
      '#title' => t('Thousand marker'),
      '#options' => $options,
      '#default_value' => $this->getSetting('thousand_separator'),
      '#weight' => 0,
    );

    $elements['prefix_suffix'] = array(
      '#type' => 'checkbox',
      '#title' => t('Display prefix and suffix.'),
      '#default_value' => $this->getSetting('prefix_suffix'),
      '#weight' => 10,
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $summary[] = $this->numberFormat(1234.1234567890);
    if ($this->getSetting('prefix_suffix')) {
      $summary[] = t('Display with prefix and suffix.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(EntityInterface $entity, $langcode, FieldInterface $items) {
    $elements = array();
    $settings = $this->getFieldSettings();

    foreach ($items as $delta => $item) {
      $output = $this->numberFormat($item->value);

      // Account for prefix and suffix.
      if ($this->getSetting('prefix_suffix')) {
        $prefixes = isset($settings['prefix']) ? array_map('field_filter_xss', explode('|', $settings['prefix'])) : array('');
        $suffixes = isset($settings['suffix']) ? array_map('field_filter_xss', explode('|', $settings['suffix'])) : array('');
        $prefix = (count($prefixes) > 1) ? format_plural($item->value, $prefixes[0], $prefixes[1]) : $prefixes[0];
        $suffix = (count($suffixes) > 1) ? format_plural($item->value, $suffixes[0], $suffixes[1]) : $suffixes[0];
        $output = $prefix . $output . $suffix;
      }

      $elements[$delta] = array('#markup' => $output);
    }

    return $elements;
  }

  /**
   * Formats a number.
   *
   * @param mixed $number
   *   The numeric value.
   *
   * @return string
   *   The formatted number.
   */
  abstract protected function numberFormat($number);
}
