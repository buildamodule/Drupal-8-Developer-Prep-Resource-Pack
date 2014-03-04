<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\relationship\RelationshipPluginBase.
 */

namespace Drupal\views\Plugin\views\relationship;

use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\HandlerBase;
use Drupal\views\Join;
use Drupal\Component\Annotation\Plugin;
use Drupal\views\Views;

/**
 * @defgroup views_relationship_handlers Views relationship handlers
 * @{
 * Handlers to tell Views how to create alternate relationships.
 */

/**
 * Simple relationship handler that allows a new version of the primary table
 * to be linked in.
 *
 * The base relationship handler can only handle a single join. Some relationships
 * are more complex and might require chains of joins; for those, you must
 * utilize a custom relationship handler.
 *
 * Definition items:
 * - base: The new base table this relationship will be adding. This does not
 *   have to be a declared base table, but if there are no tables that
 *   utilize this base table, it won't be very effective.
 * - base field: The field to use in the relationship; if left out this will be
 *   assumed to be the primary field.
 * - relationship table: The actual table this relationship operates against.
 *   This is analogous to using a 'table' override.
 * - relationship field: The actual field this relationship operates against.
 *   This is analogous to using a 'real field' override.
 * - label: The default label to provide for this relationship, which is
 *   shown in parentheses next to any field/sort/filter/argument that uses
 *   the relationship.
 *
 * @ingroup views_relationship_handlers
 */
abstract class RelationshipPluginBase extends HandlerBase {

  /**
   * Overrides \Drupal\views\Plugin\views\HandlerBase::init().
   *
   * Init handler to let relationships live on tables other than
   * the table they operate on.
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    if (isset($this->definition['relationship table'])) {
      $this->table = $this->definition['relationship table'];
    }
    if (isset($this->definition['relationship field'])) {
      // Set both realField and field so custom handler can rely on the old
      // field value.
      $this->realField = $this->field = $this->definition['relationship field'];
    }
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    // Relationships definitions should define a default label, but if they aren't get another default value.
    if (!empty($this->definition['label'])) {
      $label = $this->definition['label'];
    }
    else {
      $label = !empty($this->definition['field']) ? $this->definition['field'] : $this->definition['base field'];
    }

    $options['admin_label']['default'] = $label;
    $options['required'] = array('default' => FALSE, 'bool' => TRUE);

    return $options;
  }

  /**
   * Default options form that provides the label widget that all fields
   * should have.
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    unset($form['admin_label']['#fieldset']);
    $form['admin_label']['#weight'] = -1;

    $form['required'] = array(
      '#type' => 'checkbox',
      '#title' => t('Require this relationship'),
      '#description' => t('Enable to hide items that do not contain this relationship'),
      '#default_value' => !empty($this->options['required']),
    );
  }

  /**
   * Called to implement a relationship in a query.
   */
  public function query() {
    // Figure out what base table this relationship brings to the party.
    $table_data = Views::viewsData()->get($this->definition['base']);
    $base_field = empty($this->definition['base field']) ? $table_data['table']['base']['field'] : $this->definition['base field'];

    $this->ensureMyTable();

    $def = $this->definition;
    $def['table'] = $this->definition['base'];
    $def['field'] = $base_field;
    $def['left_table'] = $this->tableAlias;
    $def['left_field'] = $this->realField;
    $def['adjusted'] = TRUE;
    if (!empty($this->options['required'])) {
      $def['type'] = 'INNER';
    }

    if (!empty($this->definition['extra'])) {
      $def['extra'] = $this->definition['extra'];
    }

    if (!empty($def['join_id'])) {
      $id = $def['join_id'];
    }
    else {
      $id = 'standard';
    }
    $join = Views::pluginManager('join')->createInstance($id, $def);

    // use a short alias for this:
    $alias = $def['table'] . '_' . $this->table;

    $this->alias = $this->query->addRelationship($alias, $join, $this->definition['base'], $this->relationship);

    // Add access tags if the base table provide it.
    if (empty($this->query->options['disable_sql_rewrite']) && isset($table_data['table']['base']['access query tag'])) {
      $access_tag = $table_data['table']['base']['access query tag'];
      $this->query->addTag($access_tag);
    }
  }

  /**
   * You can't groupby a relationship.
   */
  public function usesGroupBy() {
    return FALSE;
  }

}

/**
 * @}
 */
