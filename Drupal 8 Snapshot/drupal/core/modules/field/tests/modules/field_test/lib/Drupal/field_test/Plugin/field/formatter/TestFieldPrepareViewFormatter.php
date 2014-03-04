<?php

/**
 * @file
 * Contains \Drupal\field_test\Plugin\field\formatter\TestFieldPrepareViewFormatter.
 */

namespace Drupal\field_test\Plugin\field\formatter;

use Drupal\field\Annotation\FieldFormatter;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldInterface;

/**
 * Plugin implementation of the 'field_test_with_prepare_view' formatter.
 *
 * @FieldFormatter(
 *   id = "field_test_with_prepare_view",
 *   label = @Translation("With prepare step"),
 *   description = @Translation("Tests prepareView() method"),
 *   field_types = {
 *     "test_field"
 *   },
 *   settings = {
 *     "test_formatter_setting_additional" = "dummy test string"
 *   }
 * )
 */
class TestFieldPrepareViewFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, array &$form_state) {
    $element['test_formatter_setting_additional'] = array(
      '#title' => t('Setting'),
      '#type' => 'textfield',
      '#size' => 20,
      '#default_value' => $this->getSetting('test_formatter_setting_additional'),
      '#required' => TRUE,
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    $summary[] = t('@setting: @value', array('@setting' => 'test_formatter_setting_additional', '@value' => $this->getSetting('test_formatter_setting_additional')));
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities, $langcode, array $items) {
    foreach ($entities as $id => $entity) {
      foreach ($items[$id] as $delta => $item) {
        // Don't add anything on empty values.
        if (!$item->isEmpty()) {
          $item->additional_formatter_value = $item->value + 1;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(EntityInterface $entity, $langcode, FieldInterface $items) {
    $elements = array();

    foreach ($items as $delta => $item) {
      $elements[$delta] = array('#markup' => $this->getSetting('test_formatter_setting_additional') . '|' . $item->value . '|' . $item->additional_formatter_value);
    }

    return $elements;
  }
}
