<?php

/**
 * @file
 * Definition of Drupal\Core\Template\Attribute.
 */

namespace Drupal\Core\Template;


/**
 * A class that can be used for collecting then rendering HTML attributtes.
 *
 * To use, one may both pass in an array of already defined attributes and
 * add attributes to it like using array syntax.
 * @code
 *  $attributes = new Attribute(array('id' => 'socks'));
 *  $attributes['class'] = array('black-cat', 'white-cat');
 *  $attributes['class'][] = 'black-white-cat';
 *  echo '<cat ' . $attributes . '>';
 *  // Produces <cat id="socks" class="black-cat white-cat black-white-cat">
 * @endcode
 *
 * individual parts of the attribute may be printed first.
 * @code
 *  $attributes = new Attribute(array('id' => 'socks'));
 *  $attributes['class'] = array('black-cat', 'white-cat');
 *  $attributes['class'][] = 'black-white-cat';
 *  echo '<cat class="cat ' . $attributes['class'] . '" ' . $attributes . '>';
 *  // Produces <cat class="cat black-cat white-cat black-white-cat" id="socks">
 * @endcode
 */
class Attribute implements \ArrayAccess, \IteratorAggregate {

  /**
   * Stores the attribute data.
   *
   * @var array
   */
  protected $storage = array();

  /**
   * Constructs a \Drupal\Core\Template\Attribute object.
   *
   * @param array $attributes
   *   An associative array of key-value pairs to be converted to attributes.
   */
  public function __construct($attributes = array()) {
    foreach ($attributes as $name => $value) {
      $this->offsetSet($name, $value);
    }
  }

  /**
   * Implements ArrayAccess::offsetGet().
   */
  public function offsetGet($name) {
    if (isset($this->storage[$name])) {
      return $this->storage[$name];
    }
  }

  /**
   * Implements ArrayAccess::offsetSet().
   */
  public function offsetSet($name, $value) {
    if (is_array($value)) {
      $value = new AttributeArray($name, $value);
    }
    elseif (is_bool($value)) {
      $value = new AttributeBoolean($name, $value);
    }
    elseif (!is_object($value)) {
      $value = new AttributeString($name, $value);
    }
    // The $name could be NULL.
    if (isset($name)) {
      $this->storage[$name] = $value;
    }
    else {
      $this->storage[] = $value;
    }
  }

  /**
   * Implements ArrayAccess::offsetUnset().
   */
  public function offsetUnset($name) {
    unset($this->storage[$name]);
  }

  /**
   * Implements ArrayAccess::offsetExists().
   */
  public function offsetExists($name) {
    return isset($this->storage[$name]);
  }

  /**
   * Implements the magic __toString() method.
   */
  public function __toString() {
    $return = '';
    foreach ($this->storage as $name => $value) {
      if (!$value->printed()) {
        $rendered = is_object($value) ? $value->render() : (check_plain($name) . ' = "' . check_plain($value) . '"');
        if ($rendered) {
          $return .= " $rendered";
        }
      }
    }
    return $return;
  }

  /**
   * Implements the magic __clone() method.
   */
  public function  __clone() {
    foreach ($this->storage as $name => $value) {
      if (is_object($value)) {
        $this->storage[$name] = clone $value;
      }
    }
  }

  /**
   * Implements IteratorAggregate::getIterator().
   */
  public function getIterator() {
    return new \ArrayIterator($this->storage);
  }

  /**
   * Returns the whole array.
   */
  public function value() {
    return $this->value;
  }

}
