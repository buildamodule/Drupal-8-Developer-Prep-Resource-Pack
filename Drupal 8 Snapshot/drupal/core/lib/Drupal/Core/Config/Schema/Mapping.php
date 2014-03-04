<?php

/**
 * @file
 * Contains \Drupal\Core\Config\Schema\Mapping.
 */

namespace Drupal\Core\Config\Schema;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use \InvalidArgumentException;

/**
 * Defines a mapping configuration element.
 *
 * Wraps configuration data and metadata allowing access to configuration data
 * using the ComplexDataInterface API. This object may contain any number and
 * type of nested properties.
 */
class Mapping extends ArrayElement implements ComplexDataInterface {

  /**
   * Overrides ArrayElement::parse()
   */
  protected function parse() {
    $elements = array();
    foreach ($this->definition['mapping'] as $key => $definition) {
      if (isset($this->value[$key])) {
        $elements[$key] = $this->parseElement($key, $this->value[$key], $definition);
      }
    }
    return $elements;
  }

  /**
   * Implements Drupal\Core\TypedData\ComplexDataInterface::get().
   */
  public function get($property_name) {
    $elements = $this->getElements();
    if (isset($elements[$property_name])) {
      return $elements[$property_name];
    }
    else {
      throw new InvalidArgumentException(format_string("The configuration property @key doesn't exist.", array(
          '@key' => $property_name,
      )));
    }
  }

  /**
   * Implements Drupal\Core\TypedData\ComplexDataInterface::set().
   */
  public function set($property_name, $value, $notify = TRUE) {
    // Set the data into the configuration array but behave according to the
    // interface specification when we've got a null value.
    if (isset($value)) {
      $this->value[$property_name] = $value;
      $property = $this->get($property_name);
    }
    else {
      // In these objects, when clearing the value, the property is gone.
      // As this needs to return a property, we get it before we delete it.
      $property = $this->get($property_name);
      unset($this->value[$property_name]);
      $property->setValue($value);
    }
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
    return $property;
  }

  /**
   * Implements Drupal\Core\TypedData\ComplexDataInterface::getProperties().
   */
  public function getProperties($include_computed = FALSE) {
    return $this->getElements();
  }

  /**
   * Implements Drupal\Core\TypedData\ComplexDataInterface::getPropertyValues().
   */
  public function getPropertyValues() {
    return $this->getValue();
  }

  /**
   * Implements Drupal\Core\TypedData\ComplexDataInterface::setPropertyValues().
   */
  public function setPropertyValues($values) {
    foreach ($values as $name => $value) {
      $this->value[$name] = $value;
    }
    return $this;
  }

  /**
   * Implements Drupal\Core\TypedData\ComplexDataInterface::getPropertyDefinition().
   */
  public function getPropertyDefinition($name) {
    if (isset($this->definition['mapping'][$name])) {
      return $this->definition['mapping'][$name];
    }
    else {
      return FALSE;
    }
  }

  /**
   * Implements Drupal\Core\TypedData\ComplexDataInterface::getPropertyDefinitions().
   */
  public function getPropertyDefinitions() {
    $list = array();
    foreach ($this->getAllKeys() as $key) {
      $list[$key] = $this->getPropertyDefinition($key);
    }
    return $list;
  }

  /**
   * Implements Drupal\Core\TypedData\ComplexDataInterface::isEmpty().
   */
  public function isEmpty() {
    return empty($this->value);
  }

  /**
   * Implements \Drupal\Core\TypedData\ComplexDataInterface::onChange().
   */
  public function onChange($property_name) {
    // Notify the parent of changes.
    if (isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

}
