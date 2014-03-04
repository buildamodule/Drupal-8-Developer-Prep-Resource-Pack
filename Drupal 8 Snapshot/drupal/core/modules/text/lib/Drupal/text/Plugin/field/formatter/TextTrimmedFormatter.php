<?php

/**
 * @file
 *
 * Contains \Drupal\text\Plugin\field\formatter\TextTrimmedFormatter.
 */
namespace Drupal\text\Plugin\field\formatter;

use Drupal\field\Annotation\FieldFormatter;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldInterface;

/**
 * Plugin implementation of the 'text_trimmed'' formatter.
 *
 * Note: This class also contains the implementations used by the
 * 'text_summary_or_trimmed' formatter.
 *
 * @see Drupal\text\Field\Formatter\TextSummaryOrTrimmedFormatter
 *
 * @FieldFormatter(
 *   id = "text_trimmed",
 *   label = @Translation("Trimmed"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary"
 *   },
 *   settings = {
 *     "trim_length" = "600"
 *   },
 *   edit = {
 *     "editor" = "form"
 *   }
 * )
 */
class TextTrimmedFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, array &$form_state) {
    $element['trim_length'] = array(
      '#title' => t('Trim length'),
      '#type' => 'number',
      '#default_value' => $this->getSetting('trim_length'),
      '#min' => 1,
      '#required' => TRUE,
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    $summary[] = t('Trim length: @trim_length', array('@trim_length' => $this->getSetting('trim_length')));
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(EntityInterface $entity, $langcode, FieldInterface $items) {
    $elements = array();

    $text_processing = $this->getFieldSetting('text_processing');
    foreach ($items as $delta => $item) {
      if ($this->getPluginId() == 'text_summary_or_trimmed' && !empty($item->summary)) {
        $output = text_sanitize($text_processing, $langcode, $item->getValue(TRUE), 'summary');
      }
      else {
        $output = text_sanitize($text_processing, $langcode, $item->getValue(TRUE), 'value');
        $output = text_summary($output, $text_processing ? $item->format : NULL, $this->getSetting('trim_length'));
      }
      $elements[$delta] = array('#markup' => $output);
    }

    return $elements;
  }

}
