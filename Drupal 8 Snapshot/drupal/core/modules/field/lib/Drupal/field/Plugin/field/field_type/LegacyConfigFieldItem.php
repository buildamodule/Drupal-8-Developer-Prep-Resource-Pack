<?php

/**
 * @file
 * Contains \Drupal\field\Plugin\field\field_type\LegacyConfigFieldItem.
 */

namespace Drupal\field\Plugin\field\field_type;

use Drupal\Core\Entity\Field\PrepareCacheInterface;
use Drupal\field\Plugin\Type\FieldType\ConfigFieldItemBase;
use Drupal\field\FieldInterface;

/**
 * Plugin implementation for legacy field types.
 *
 * This special implementation acts as a temporary BC layer for field types
 * that have not been converted to Plugins, and bridges new methods to the
 * old-style hook_field_*() callbacks.
 *
 * This class is not discovered by the annotations reader, but referenced by
 * the Drupal\field\Plugin\Discovery\LegacyDiscoveryDecorator.
 *
 * @todo Remove once all core field types have been converted (see
 * http://drupal.org/node/2014671).
 */
abstract class LegacyConfigFieldItem extends ConfigFieldItemBase implements PrepareCacheInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldInterface $field) {
    $definition = \Drupal::service('plugin.manager.entity.field.field_type')->getDefinition($field->type);
    $module = $definition['provider'];
    module_load_install($module);
    $callback = "{$module}_field_schema";
    if (function_exists($callback)) {
      return $callback($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $callback = $this->getLegacyCallback('is_empty');
    // Make sure the array received by the legacy callback includes computed
    // properties.
    $item = $this->getValue(TRUE);
    // The previous hook was never called on an empty item, but EntityNG always
    // creates a FieldItem element for an empty field.
    return empty($item) || $callback($item, $this->getInstance()->getField()->type);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, array &$form_state, $has_data) {
    if ($callback = $this->getLegacyCallback('settings_form')) {
      // hook_field_settings_form() used to receive the $instance (not actually
      // needed), and the value of field_has_data().
      return $callback($this->getInstance()->getField(), $this->getInstance(), $has_data);
    }
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function instanceSettingsForm(array $form, array &$form_state) {
    if ($callback = $this->getLegacyCallback('instance_settings_form')) {
      return $callback($this->getInstance()->getField(), $this->getInstance(), $form_state);
    }
    return array();
  }

  /**
   * Massages loaded field values before they enter the field cache.
   *
   * This implements the prepareCache() method defined in PrepareCacheInterface
   * even if the class does explicitly implements it, so as to preserve
   * the optimizations of only creating Field and FieldItem objects and invoking
   * the method if are actually needed.
   *
   * @see \Drupal\Core\Entity\DatabaseStorageController::invokeFieldItemPrepareCache()
   */
  public function prepareCache() {
    if ($callback = $this->getLegacyCallback('load')) {
      $entity = $this->getParent()->getParent();
      $langcode = $entity->language()->id;
      $entity_id = $entity->id();

      // hook_field_attach_load() receives items keyed by entity id, and alter
      // then by reference.
      $items = array($entity_id => array(0 => $this->getValue(TRUE)));
      $args = array(
        $entity->entityType(),
        array($entity_id => $entity),
        $this->getInstance()->getField(),
        array($entity_id => $this->getInstance()),
        $langcode,
        &$items,
        FIELD_LOAD_CURRENT,
      );
      call_user_func_array($callback, $args);
      $this->setValue($items[$entity_id][0]);
    }
  }

  /**
   * Returns the legacy callback for a given field type "hook".
   *
   * @param string $hook
   *   The name of the hook, e.g. 'settings_form', 'is_empty'.
   *
   * @return string|null
   *   The name of the legacy callback, or NULL if it does not exist.
   */
  protected function getLegacyCallback($hook) {
    $definition = $this->getPluginDefinition();
    $module = $definition['provider'];
    $callback = "{$module}_field_{$hook}";
    if (function_exists($callback)) {
      return $callback;
    }
  }

}
