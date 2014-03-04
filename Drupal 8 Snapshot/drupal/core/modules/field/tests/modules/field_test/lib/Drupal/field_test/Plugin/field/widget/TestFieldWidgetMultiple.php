<?php

/**
 * @file
 * Definition of Drupal\field_test\Plugin\field\widget\TestFieldWidgetMultiple.
 */

namespace Drupal\field_test\Plugin\field\widget;


use Drupal\field\Annotation\FieldWidget;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\Field\FieldInterface;
use Drupal\field\Plugin\Type\Widget\WidgetBase;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'test_field_widget_multiple' widget.
 *
 * The 'field_types' entry is left empty, and is populated through hook_field_widget_info_alter().
 *
 * @see field_test_field_widget_info_alter()
 *
 * @FieldWidget(
 *   id = "test_field_widget_multiple",
 *   label = @Translation("Test widget - multiple"),
 *   settings = {
 *     "test_widget_setting_multiple" = "dummy test string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class TestFieldWidgetMultiple extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, array &$form_state) {
    $element['test_widget_setting_multiple'] = array(
      '#type' => 'textfield',
      '#title' => t('Field test field widget setting'),
      '#description' => t('A dummy form element to simulate field widget setting.'),
      '#default_value' => $this->getSetting('test_widget_setting_multiple'),
      '#required' => FALSE,
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    $summary[] = t('@setting: @value', array('@setting' => 'test_widget_setting_multiple', '@value' => $this->getSetting('test_widget_setting_multiple')));
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldInterface $items, $delta, array $element, $langcode, array &$form, array &$form_state) {
    $values = array();
    foreach ($items as $delta => $item) {
      $values[] = $item->value;
    }
    $element += array(
      '#type' => 'textfield',
      '#default_value' => implode(', ', $values),
      '#element_validate' => array('field_test_widget_multiple_validate'),
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, array &$form_state) {
    return $element;
  }

}
