<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\query\QueryPluginBase.
 */

namespace Drupal\views\Plugin\views\query;

use Drupal\views\Plugin\views\PluginBase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * @todo.
 */
abstract class QueryPluginBase extends PluginBase implements QueryInterface {

  /**
   * A pager plugin that should be provided by the display.
   *
   * @var views_plugin_pager
   */
  var $pager = NULL;

  /**
   * Stores the limit of items that should be requested in the query.
   *
   * @var int
   */
  protected $limit;

  /**
   * Generate a query and a countquery from all of the information supplied
   * to the object.
   *
   * @param $get_count
   *   Provide a countquery if this is true, otherwise provide a normal query.
   */
  public function query($get_count = FALSE) { }

  /**
   * Let modules modify the query just prior to finalizing it.
   *
   * @param view $view
   *   The view which is executed.
   */
  function alter(ViewExecutable $view) {  }

  /**
   * Builds the necessary info to execute the query.
   *
   * @param view $view
   *   The view which is executed.
   */
  function build(ViewExecutable $view) { }

  /**
   * Executes the query and fills the associated view object with according
   * values.
   *
   * Values to set: $view->result, $view->total_rows, $view->execute_time,
   * $view->pager['current_page'].
   *
   * $view->result should contain an array of objects. The array must use a
   * numeric index starting at 0.
   *
   * @param view $view
   *   The view which is executed.
   */
  function execute(ViewExecutable $view) {  }

  /**
   * Add a signature to the query, if such a thing is feasible.
   *
   * This signature is something that can be used when perusing query logs to
   * discern where particular queries might be coming from.
   *
   * @param view $view
   *   The view which is executed.
   */
  public function addSignature(ViewExecutable $view) { }

  /**
   * Get aggregation info for group by queries.
   *
   * If NULL, aggregation is not allowed.
   */
  public function getAggregationInfo() { }

  public function validateOptionsForm(&$form, &$form_state) { }

  public function submitOptionsForm(&$form, &$form_state) { }

  public function summaryTitle() {
    return t('Settings');
  }

  /**
   * Set a LIMIT on the query, specifying a maximum number of results.
   */
  public function setLimit($limit) {
    $this->limit = $limit;
  }

  /**
   * Set an OFFSET on the query, specifying a number of results to skip
   */
  public function setOffset($offset) {
    $this->offset = $offset;
  }

  /**
   * Returns the limit of the query.
   */
  public function getLimit() {
    return $this->limit;
  }

  /**
   * Create a new grouping for the WHERE or HAVING clause.
   *
   * @param $type
   *   Either 'AND' or 'OR'. All items within this group will be added
   *   to the WHERE clause with this logical operator.
   * @param $group
   *   An ID to use for this group. If unspecified, an ID will be generated.
   * @param $where
   *   'where' or 'having'.
   *
   * @return $group
   *   The group ID generated.
   */
  public function setWhereGroup($type = 'AND', $group = NULL, $where = 'where') {
    // Set an alias.
    $groups = &$this->$where;

    if (!isset($group)) {
      $group = empty($groups) ? 1 : max(array_keys($groups)) + 1;
    }

    // Create an empty group
    if (empty($groups[$group])) {
      $groups[$group] = array('conditions' => array(), 'args' => array());
    }

    $groups[$group]['type'] = strtoupper($type);
    return $group;
  }

  /**
   * Control how all WHERE and HAVING groups are put together.
   *
   * @param $type
   *   Either 'AND' or 'OR'
   */
  public function setGroupOperator($type = 'AND') {
    $this->group_operator = strtoupper($type);
  }

  /**
   * Loads all entities contained in the passed-in $results.
   *.
   * If the entity belongs to the base table, then it gets stored in
   * $result->_entity. Otherwise, it gets stored in
   * $result->_relationship_entities[$relationship_id];
   *
   * Query plugins that don't support entities can leave the method empty.
   */
  function loadEntities(&$results) {}

  /**
   * Returns a Unix timestamp to database native timestamp expression.
   *
   * @param string $field
   *   The query field that will be used in the expression.
   *
   * @return string
   *   An expression representing a timestamp with time zone.
   */
  public function getDateField($field) {
    return $field;
  }

  /**
   * Set the database to the current user timezone,
   *
   * @return string
   *   The current timezone as returned by drupal_get_user_timezone().
   */
  public function setupTimezone() {
    return drupal_get_user_timezone();
  }

  /**
   * Creates cross-database date formatting.
   *
   * @param string $field
   *   An appropriate query expression pointing to the date field.
   * @param string $format
   *   A format string for the result, like 'Y-m-d H:i:s'.
   *
   * @return string
   *   A string representing the field formatted as a date in the format
   *   specified by $format.
   */
  public function getDateFormat($field, $format) {
    return $field;
  }

}
