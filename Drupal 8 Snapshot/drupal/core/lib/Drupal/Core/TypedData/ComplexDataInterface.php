<?php

/**
 * @file
 * Contains \Drupal\Core\TypedData\ComplexDataInterface.
 */

namespace Drupal\Core\TypedData;

use Traversable;

/**
 * Interface for complex data; i.e. data containing named and typed properties.
 *
 * The name of a property has to be a valid PHP variable name, starting with
 * an alphabetic character.
 *
 * This is implemented by entities as well as by field item classes of
 * entities.
 *
 * When implementing this interface which extends Traversable, make sure to list
 * IteratorAggregate or Iterator before this interface in the implements clause.
 */
interface ComplexDataInterface extends Traversable, TypedDataInterface  {

  /**
   * Gets a property object.
   *
   * @param $property_name
   *   The name of the property to get; e.g., 'title' or 'name'.
   *
   * @throws \InvalidArgumentException
   *   If an invalid property name is given.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The property object.
   */
  public function get($property_name);

  /**
   * Sets a property value.
   *
   * @param $property_name
   *   The name of the property to set; e.g., 'title' or 'name'.
   * @param $value
   *   The value to set, or NULL to unset the property.
   * @param bool $notify
   *   (optional) Whether to notify the parent object of the change. Defaults to
   *   TRUE. If the update stems from a parent object, set it to FALSE to avoid
   *   being notified again.
   *
   * @throws \InvalidArgumentException
   *   If the specified property does not exist.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The property object.
   */
  public function set($property_name, $value, $notify = TRUE);

  /**
   * Gets an array of property objects.
   *
   * @param bool $include_computed
   *   If set to TRUE, computed properties are included. Defaults to FALSE.
   *
   * @return array
   *   An array of property objects implementing the TypedDataInterface, keyed
   *   by property name.
   */
  public function getProperties($include_computed = FALSE);

  /**
   * Gets an array of property values.
   *
   * Gets an array of plain property values including all not-computed
   * properties.
   *
   * @return array
   *   An array keyed by property name containing the property value.
   */
  public function getPropertyValues();

  /**
   * Sets multiple property values.
   *
   * @param array
   *   The array of property values to set, keyed by property name.
   *
   * @throws \InvalidArgumentException
   *   If the value of a not existing property is to be set.
   * @throws \Drupal\Core\TypedData\ReadOnlyException
   *   If a read-only property is set.
   */
  public function setPropertyValues($values);

  /**
   * Gets the definition of a contained property.
   *
   * @param string $name
   *   The name of property.
   *
   * @return array|FALSE
   *   The definition of the property or FALSE if the property does not exist.
   */
  public function getPropertyDefinition($name);

  /**
   * Gets an array of property definitions of contained properties.
   *
   * @param array $definition
   *   The definition of the container's property, e.g. the definition of an
   *   entity reference property.
   *
   * @return array
   *   An array of property definitions of contained properties, keyed by
   *   property name.
   */
  public function getPropertyDefinitions();

  /**
   * Determines whether the data structure is empty.
   *
   * @return boolean
   *   TRUE if the data structure is empty, FALSE otherwise.
   */
  public function isEmpty();

  /**
   * React to changes to a child property.
   *
   * Note that this is invoked after any changes have been applied.
   *
   * @param $property_name
   *   The name of the property which is changed.
   */
  public function onChange($property_name);
}
