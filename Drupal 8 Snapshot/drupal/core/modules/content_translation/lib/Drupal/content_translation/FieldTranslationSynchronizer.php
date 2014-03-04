<?php

/**
 * @file
 * Contains \Drupal\content_translation\FieldTranslationSynchronizer.
 */

namespace Drupal\content_translation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Entity\EntityNG;

/**
 * Provides field translation synchronization capabilities.
 */
class FieldTranslationSynchronizer implements FieldTranslationSynchronizerInterface {

  /**
   * The entity manager to use to load unchanged entities.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * Constructs a FieldTranslationSynchronizer object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entityManager
   *   The entity manager.
   */
  public function __construct(EntityManager $entityManager) {
    $this->entityManager = $entityManager;
  }

  /**
   * {@inheritdoc}
   */
  public function synchronizeFields(EntityInterface $entity, $sync_langcode, $original_langcode = NULL) {
    // Field synchronization is only supported for NG entities.
    $entity = $entity->getNGEntity();
    if (!($entity instanceof EntityNG)) {
      return;
    }

    $translations = $entity->getTranslationLanguages();

    // If we have no information about what to sync to, if we are creating a new
    // entity, if we have no translations for the current entity and we are not
    // creating one, then there is nothing to synchronize.
    if (empty($sync_langcode) || $entity->isNew() || count($translations) < 2) {
      return;
    }

    // If the entity language is being changed there is nothing to synchronize.
    $entity_type = $entity->entityType();
    $entity_unchanged = isset($entity->original) ? $entity->original : $this->entityManager->getStorageController($entity_type)->loadUnchanged($entity->id());
    if ($entity->getUntranslated()->language()->id != $entity_unchanged->getUntranslated()->language()->id) {
      return;
    }

    // @todo Use Entity Field API to retrieve field definitions.
    $instances = field_info_instances($entity_type, $entity->bundle());
    foreach ($instances as $field_name => $instance) {
      $field = $instance->getField();

      // Sync when the field is not empty, when the synchronization translations
      // setting is set, and the field is translatable.
      if (!$entity->get($field_name)->isEmpty() && !empty($instance['settings']['translation_sync']) && field_is_translatable($entity_type, $field)) {
        // Retrieve all the untranslatable column groups and merge them into
        // single list.
        $groups = array_keys(array_diff($instance['settings']['translation_sync'], array_filter($instance['settings']['translation_sync'])));
        if (!empty($groups)) {
          $columns = array();
          foreach ($groups as $group) {
            $info = $field['settings']['column_groups'][$group];
            // A missing 'columns' key indicates we have a single-column group.
            $columns = array_merge($columns, isset($info['columns']) ? $info['columns'] : array($group));
          }
          if (!empty($columns)) {
            $values = array();
            foreach ($translations as $langcode => $language) {
              $values[$langcode] = $entity->getTranslation($langcode)->get($field_name)->getValue();
            }

            // If a translation is being created, the original values should be
            // used as the unchanged items. In fact there are no unchanged items
            // to check against.
            $langcode = $original_langcode ?: $sync_langcode;
            $unchanged_items = $entity_unchanged->getTranslation($langcode)->get($field_name)->getValue();
            $this->synchronizeItems($values, $unchanged_items, $sync_langcode, array_keys($translations), $columns);

            foreach ($translations as $langcode => $language) {
              $entity->getTranslation($langcode)->get($field_name)->setValue($values[$langcode]);
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function synchronizeItems(array &$values, array $unchanged_items, $sync_langcode, array $translations, array $columns) {
    $source_items = $values[$sync_langcode];

    // Make sure we can detect any change in the source items.
    $change_map = array();

    // By picking the maximum size between updated and unchanged items, we make
    // sure to process also removed items.
    $total = max(array(count($source_items), count($unchanged_items)));

    // As a first step we build a map of the deltas corresponding to the column
    // values to be synchronized. Recording both the old values and the new
    // values will allow us to detect any change in the order of the new items
    // for each column.
    for ($delta = 0; $delta < $total; $delta++) {
      foreach (array('old' => $unchanged_items, 'new' => $source_items) as $key => $items) {
        if ($item_id = $this->itemHash($items, $delta, $columns)) {
          $change_map[$item_id][$key][] = $delta;
        }
      }
    }

    // Backup field values and the change map.
    $original_field_values = $values;
    $original_change_map = $change_map;

    // Reset field values so that no spurious one is stored. Source values must
    // be preserved in any case.
    $values = array($sync_langcode => $source_items);

    // Update field translations.
    foreach ($translations as $langcode) {

      // We need to synchronize only values different from the source ones.
      if ($langcode != $sync_langcode) {
        // Reinitialize the change map as it is emptied while processing each
        // language.
        $change_map = $original_change_map;

        // By using the maximum cardinality we ensure to process removed items.
        for ($delta = 0; $delta < $total; $delta++) {
          // By inspecting the map we built before we can tell whether a value
          // has been created or removed. A changed value will be interpreted as
          // a new value, in fact it did not exist before.
          $created = TRUE;
          $removed = TRUE;
          $old_delta = NULL;
          $new_delta = NULL;

          if ($item_id = $this->itemHash($source_items, $delta, $columns)) {
            if (!empty($change_map[$item_id]['old'])) {
              $old_delta = array_shift($change_map[$item_id]['old']);
            }
            if (!empty($change_map[$item_id]['new'])) {
              $new_delta = array_shift($change_map[$item_id]['new']);
            }
            $created = $created && !isset($old_delta);
            $removed = $removed && !isset($new_delta);
          }

          // If an item has been removed we do not store its translations.
          if ($removed) {
            continue;
          }
          // If a synchronized column has changed or has been created from
          // scratch we need to override the full items array for all languages.
          elseif ($created) {
            $values[$langcode][$delta] = $source_items[$delta];
          }
          // Otherwise the current item might have been reordered.
          elseif (isset($old_delta) && isset($new_delta)) {
            // If for any reason the old value is not defined for the current
            // language we fall back to the new source value, this way we ensure
            // the new values are at least propagated to all the translations.
            // If the value has only been reordered we just move the old one in
            // the new position.
            $item = isset($original_field_values[$langcode][$old_delta]) ? $original_field_values[$langcode][$old_delta] : $source_items[$new_delta];
            $values[$langcode][$new_delta] = $item;
          }
        }
      }
    }
  }

  /**
   * Computes a hash code for the specified item.
   *
   * @param array $items
   *   An array of field items.
   * @param integer $delta
   *   The delta identifying the item to be processed.
   * @param array $columns
   *   An array of column names to be synchronized.
   *
   * @returns string
   *   A hash code that can be used to identify the item.
   */
  protected function itemHash(array $items, $delta, array $columns) {
    $values = array();

    if (isset($items[$delta])) {
      foreach ($columns as $column) {
        if (!empty($items[$delta][$column])) {
          $value = $items[$delta][$column];
          // String and integer values are by far the most common item values,
          // thus we special-case them to improve performance.
          $values[] = is_string($value) || is_int($value) ? $value : hash('sha256', serialize($value));
        }
        else {
          // Explicitly track also empty values.
          $values[] = '';
        }
      }
    }

    return implode('.', $values);
  }

}
