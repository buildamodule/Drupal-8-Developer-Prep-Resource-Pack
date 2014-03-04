<?php

/**
 * @file
 * Contains \Drupal\telephone\Plugin\field\field_type\TelephoneItem.
 */

namespace Drupal\telephone\Plugin\field\field_type;

use Drupal\Core\Entity\Annotation\FieldType;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\FieldType\ConfigFieldItemBase;
use Drupal\field\FieldInterface;

/**
 * Plugin implementation of the 'telephone' field type.
 *
 * @FieldType(
 *   id = "telephone",
 *   label = @Translation("Telephone number"),
 *   description = @Translation("This field stores a telephone number in the database."),
 *   default_widget = "telephone_default",
 *   default_formatter = "telephone_link"
 * )
 */
class TelephoneItem extends ConfigFieldItemBase {

  /**
   * Definitions of the contained properties.
   *
   * @var array
   */
  static $propertyDefinitions;

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldInterface $field) {
    return array(
      'columns' => array(
        'value' => array(
          'type' => 'varchar',
          'length' => 256,
          'not null' => FALSE,
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    if (!isset(static::$propertyDefinitions)) {
      static::$propertyDefinitions['value'] = array(
        'type' => 'string',
        'label' => t('Telephone number'),
      );
    }
    return static::$propertyDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedData()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $max_length = 256;
    $constraints[] = $constraint_manager->create('ComplexData', array(
      'value' => array(
        'Length' => array(
          'max' => $max_length,
          'maxMessage' => t('%name: the telephone number may not be longer than @max characters.', array('%name' => $this->getFieldDefinition()->getFieldLabel(), '@max' => $max_length)),
        )
      ),
    ));

    return $constraints;
  }

}
