<?php

/**
 * @file
 * Contains \Drupal\user\Plugin\entity_reference\selection\UserSelection.
 */

namespace Drupal\user\Plugin\entity_reference\selection;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\entity_reference\Annotation\EntityReferenceSelection;
use Drupal\entity_reference\Plugin\entity_reference\selection\SelectionBase;

/**
 * Provides specific access control for the user entity type.
 *
 * @EntityReferenceSelection(
 *   id = "user_default",
 *   label = @Translation("User selection"),
 *   entity_types = {"user"},
 *   group = "default",
 *   weight = 1
 * )
 */
class UserSelection extends SelectionBase {

  /**
   * {@inheritdoc}
   */
  public static function settingsForm(&$field, &$instance) {
    // Merge in default values.
    $instance['settings']['handler_settings'] += array(
      'filter' => array(
        'type' => '_none',
      ),
    );

    // Add user specific filter options.
    $form['filter']['type'] = array(
      '#type' => 'select',
      '#title' => t('Filter by'),
      '#options' => array(
        '_none' => t('- None -'),
        'role' => t('User role'),
      ),
      '#ajax' => TRUE,
      '#limit_validation_errors' => array(),
      '#default_value' => $instance['settings']['handler_settings']['filter']['type'],
    );

    $form['filter']['settings'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('entity_reference-settings')),
      '#process' => array('_entity_reference_form_process_merge_parent'),
    );

    if ($instance['settings']['handler_settings']['filter']['type'] == 'role') {
      // Merge in default values.
      $instance['settings']['handler_settings']['filter'] += array(
        'role' => NULL,
      );

      $form['filter']['settings']['role'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Restrict to the selected roles'),
        '#required' => TRUE,
        '#options' => array_diff_key(user_role_names(TRUE), drupal_map_assoc(array(DRUPAL_AUTHENTICATED_RID))),
        '#default_value' => $instance['settings']['handler_settings']['filter']['role'],
      );
    }

    $form += parent::settingsForm($field, $instance);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    // The user entity doesn't have a label column.
    if (isset($match)) {
      $query->condition('name', $match, $match_operator);
    }

    // Adding the 'user_access' tag is sadly insufficient for users: core
    // requires us to also know about the concept of 'blocked' and 'active'.
    if (!user_access('administer users')) {
      $query->condition('status', 1);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(SelectInterface $query) {
    if (user_access('administer users')) {
      // In addition, if the user is administrator, we need to make sure to
      // match the anonymous user, that doesn't actually have a name in the
      // database.
      $conditions = &$query->conditions();
      foreach ($conditions as $key => $condition) {
        if ($key !== '#conjunction' && is_string($condition['field']) && $condition['field'] === 'users.name') {
          // Remove the condition.
          unset($conditions[$key]);

          // Re-add the condition and a condition on uid = 0 so that we end up
          // with a query in the form:
          // WHERE (name LIKE :name) OR (:anonymous_name LIKE :name AND uid = 0)
          $or = db_or();
          $or->condition($condition['field'], $condition['value'], $condition['operator']);
          // Sadly, the Database layer doesn't allow us to build a condition
          // in the form ':placeholder = :placeholder2', because the 'field'
          // part of a condition is always escaped.
          // As a (cheap) workaround, we separately build a condition with no
          // field, and concatenate the field and the condition separately.
          $value_part = db_and();
          $value_part->condition('anonymous_name', $condition['value'], $condition['operator']);
          $value_part->compile(Database::getConnection(), $query);
          $or->condition(db_and()
            ->where(str_replace('anonymous_name', ':anonymous_name', (string) $value_part), $value_part->arguments() + array(':anonymous_name' => user_format_name(user_load(0))))
            ->condition('users.uid', 0)
          );
          $query->condition($or);
        }
      }
    }

    // Add the filter by role option.
    if (!empty($this->instance['settings']['handler_settings']['filter'])) {
      $filter_settings = $this->instance['settings']['handler_settings']['filter'];
      if ($filter_settings['type'] == 'role') {
        $tables = $query->getTables();
        $base_table = $tables['base_table']['alias'];
        $query->join('users_roles', 'ur', $base_table . '.uid = ur.uid');
        $query->condition('ur.rid', $filter_settings['role']);
      }
    }
  }
}
