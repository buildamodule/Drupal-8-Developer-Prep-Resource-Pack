<?php

/**
 * @file
 * Contains \Drupal\number\Plugin\field\field_type\NumberItemBase.
 */

namespace Drupal\number\Plugin\field\field_type;

use Drupal\field\Plugin\Type\FieldType\ConfigFieldItemBase;

/**
 * Base class for 'number' configurable field types.
 */
abstract class NumberItemBase extends ConfigFieldItemBase {

  /**
   * Definitions of the contained properties.
   *
   * @var array
   */
  static $propertyDefinitions;

  /**
   * {@inheritdoc}
   */
  public function instanceSettingsForm(array $form, array &$form_state) {
    $element = array();
    $settings = $this->getFieldSettings();

    $element['min'] = array(
      '#type' => 'textfield',
      '#title' => t('Minimum'),
      '#default_value' => $settings['min'],
      '#description' => t('The minimum value that should be allowed in this field. Leave blank for no minimum.'),
      '#element_validate' => array('form_validate_number'),
    );
    $element['max'] = array(
      '#type' => 'textfield',
      '#title' => t('Maximum'),
      '#default_value' => $settings['max'],
      '#description' => t('The maximum value that should be allowed in this field. Leave blank for no maximum.'),
      '#element_validate' => array('form_validate_number'),
    );
    $element['prefix'] = array(
      '#type' => 'textfield',
      '#title' => t('Prefix'),
      '#default_value' => $settings['prefix'],
      '#size' => 60,
      '#description' => t("Define a string that should be prefixed to the value, like '$ ' or '&euro; '. Leave blank for none. Separate singular and plural values with a pipe ('pound|pounds')."),
    );
    $element['suffix'] = array(
      '#type' => 'textfield',
      '#title' => t('Suffix'),
      '#default_value' => $settings['suffix'],
      '#size' => 60,
      '#description' => t("Define a string that should be suffixed to the value, like ' m', ' kb/s'. Leave blank for none. Separate singular and plural values with a pipe ('pound|pounds')."),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    if (empty($this->value) && (string) $this->value !== '0') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedData()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $settings = $this->getFieldSettings();
    $label = $this->getFieldDefinition()->getFieldLabel();

    if (!empty($settings['min'])) {
      $min = $settings['min'];
      $constraints[] = $constraint_manager->create('ComplexData', array(
        'value' => array(
          'Range' => array(
            'min' => $min,
            'minMessage' => t('%name: the value may be no less than %min.', array('%name' => $label, '%min' => $min)),
          )
        ),
      ));
    }

    if (!empty($settings['max'])) {
      $max = $settings['max'];
      $constraints[] = $constraint_manager->create('ComplexData', array(
        'value' => array(
          'Range' => array(
            'max' => $max,
            'maxMessage' => t('%name: the value may be no greater than %max.', array('%name' => $label, '%max' => $max)),
          )
        ),
      ));
    }

    return $constraints;
  }

}
