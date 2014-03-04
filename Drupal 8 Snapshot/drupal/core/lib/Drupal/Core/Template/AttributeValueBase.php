<?php

/**
 * @file
 * Definition of Drupal\Core\Template\AttributeValueBase.
 */

namespace Drupal\Core\Template;

/**
 * Defines the base class for an attribute type.
 *
 * @see Drupal\Core\Template\Attribute
 */
abstract class AttributeValueBase {

  /**
   * Whether this attribute hsa been printed already.
   *
   * @var bool
   */
  protected $printed = FALSE;

  /**
   * The value itself.
   *
   * @var mixed
   */
  protected $value;

  /**
   * The name of the value.
   *
   * @var mixed
   */
  protected $name;

  /**
   * Constructs a \Drupal\Core\Template\AttributeValueBase object.
   */
  public function __construct($name, $value) {
    $this->name = $name;
    $this->value = $value;
  }

  /**
   * Returns a string representation of the attribute.
   *
   * While __toString only returns the value in a string form, render()
   * contains the name of the attribute as well.
   *
   * @return string
   *   The string representation of the attribute.
   */
  public function render() {
    return $this->name . '="' . $this . '"';
  }

  /**
   * Whether this attribute hsa been printed already.
   *
   * @return bool
   *   TRUE if this attribute has been printed, FALSE otherwise.
   */
  public function printed() {
    return $this->printed;
  }

  /**
   * Implements the magic __toString() method.
   */
  abstract function __toString();

}
