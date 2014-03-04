<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\query\Sql.
 */

namespace Drupal\views\Plugin\views\query;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Database;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\Plugin\views\HandlerBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * @todo.
 *
 * @Plugin(
 *   id = "views_query",
 *   title = @Translation("SQL Query"),
 *   help = @Translation("Query will be generated and run using the Drupal database API.")
 * )
 */
class Sql extends QueryPluginBase {

  /**
   * A list of tables in the order they should be added, keyed by alias.
   */
  var $table_queue = array();

  /**
   * Holds an array of tables and counts added so that we can create aliases
   */
  var $tables = array();

  /**
   * Holds an array of relationships, which are aliases of the primary
   * table that represent different ways to join the same table in.
   */
  var $relationships = array();

  /**
   * An array of sections of the WHERE query. Each section is in itself
   * an array of pieces and a flag as to whether or not it should be AND
   * or OR.
   */
  var $where = array();
  /**
   * An array of sections of the HAVING query. Each section is in itself
   * an array of pieces and a flag as to whether or not it should be AND
   * or OR.
   */
  var $having = array();
  /**
   * The default operator to use when connecting the WHERE groups. May be
   * AND or OR.
   */
  var $group_operator = 'AND';

  /**
   * A simple array of order by clauses.
   */
  var $orderby = array();

  /**
   * A simple array of group by clauses.
   */
  var $groupby = array();


  /**
   * An array of fields.
   */
  var $fields = array();

  /**
   * A flag as to whether or not to make the primary field distinct.
   */
  var $distinct = FALSE;

  var $has_aggregate = FALSE;

  /**
   * Should this query be optimized for counts, for example no sorts.
   */
  var $get_count_optimized = NULL;

  /**
   * An array mapping table aliases and field names to field aliases.
   */
  var $field_aliases = array();

  /**
   * Query tags which will be passed over to the dbtng query object.
   */
  var $tags = array();

  /**
   * Is the view marked as not distinct.
   *
   * @var bool
   */
  var $no_distinct;

  /**
   * Overrides \Drupal\views\Plugin\views\PluginBase::init().
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $base_table = $this->view->storage->get('base_table');
    $base_field = $this->view->storage->get('base_field');
    $this->relationships[$base_table] = array(
      'link' => NULL,
      'table' => $base_table,
      'alias' => $base_table,
      'base' => $base_table
    );

    // init the table queue with our primary table.
    $this->table_queue[$base_table] = array(
      'alias' => $base_table,
      'table' => $base_table,
      'relationship' => $base_table,
      'join' => NULL,
    );

    // init the tables with our primary table
    $this->tables[$base_table][$base_table] = array(
      'count' => 1,
      'alias' => $base_table,
    );

    $this->count_field = array(
      'table' => $base_table,
      'field' => $base_field,
      'alias' => $base_field,
      'count' => TRUE,
    );
  }

  /**
   * Set the view to be distinct (per base field).
   *
   * @param bool $value
   *   Should the view by distincted.
   */
  protected function setDistinct($value = TRUE) {
    if (!(isset($this->no_distinct) && $value)) {
      $this->distinct = $value;
    }
  }

  /**
   * Set what field the query will count() on for paging.
   */
  public function setCountField($table, $field, $alias = NULL) {
    if (empty($alias)) {
      $alias = $table . '_' . $field;
    }
    $this->count_field = array(
      'table' => $table,
      'field' => $field,
      'alias' => $alias,
      'count' => TRUE,
    );
  }

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['disable_sql_rewrite'] = array(
      'default' => FALSE,
      'translatable' => FALSE,
      'bool' => TRUE,
    );
    $options['distinct'] = array(
      'default' => FALSE,
      'bool' => TRUE,
    );
    $options['slave'] = array(
      'default' => FALSE,
      'bool' => TRUE,
    );
    $options['query_comment'] = array(
      'default' => '',
    );
    $options['query_tags'] = array(
      'default' => array(),
    );

    return $options;
  }

  /**
   * Add settings for the ui.
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['disable_sql_rewrite'] = array(
      '#title' => t('Disable SQL rewriting'),
      '#description' => t('Disabling SQL rewriting will disable node_access checks as well as other modules that implement hook_query_alter().'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->options['disable_sql_rewrite']),
      '#suffix' => '<div class="messages messages--warning sql-rewrite-warning js-hide">' . t('WARNING: Disabling SQL rewriting means that node access security is disabled. This may allow users to see data they should not be able to see if your view is misconfigured. Use this option only if you understand and accept this security risk.') . '</div>',
    );
    $form['distinct'] = array(
      '#type' => 'checkbox',
      '#title' => t('Distinct'),
      '#description' => t('This will make the view display only distinct items. If there are multiple identical items, each will be displayed only once. You can use this to try and remove duplicates from a view, though it does not always work. Note that this can slow queries down, so use it with caution.'),
      '#default_value' => !empty($this->options['distinct']),
    );
    $form['slave'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use Slave Server'),
      '#description' => t('This will make the query attempt to connect to a slave server if available.  If no slave server is defined or available, it will fall back to the default server.'),
      '#default_value' => !empty($this->options['slave']),
    );
    $form['query_comment'] = array(
      '#type' => 'textfield',
      '#title' => t('Query Comment'),
      '#description' => t('If set, this comment will be embedded in the query and passed to the SQL server. This can be helpful for logging or debugging.'),
      '#default_value' => $this->options['query_comment'],
    );
    $form['query_tags'] = array(
      '#type' => 'textfield',
      '#title' => t('Query Tags'),
      '#description' => t('If set, these tags will be appended to the query and can be used to identify the query in a module. This can be helpful for altering queries.'),
      '#default_value' => implode(', ', $this->options['query_tags']),
      '#element_validate' => array('views_element_validate_tags'),
    );
  }

  /**
   * Special submit handling.
   */
  public function submitOptionsForm(&$form, &$form_state) {
    $element = array('#parents' => array('query', 'options', 'query_tags'));
    $value = explode(',', NestedArray::getValue($form_state['values'], $element['#parents']));
    $value = array_filter(array_map('trim', $value));
    form_set_value($element, $value, $form_state);
  }

  /**
   * A relationship is an alternative endpoint to a series of table
   * joins. Relationships must be aliases of the primary table and
   * they must join either to the primary table or to a pre-existing
   * relationship.
   *
   * An example of a relationship would be a nodereference table.
   * If you have a nodereference named 'book_parent' which links to a
   * parent node, you could set up a relationship 'node_book_parent'
   * to 'node'. Then, anything that links to 'node' can link to
   * 'node_book_parent' instead, thus allowing all properties of
   * both nodes to be available in the query.
   *
   * @param $alias
   *   What this relationship will be called, and is also the alias
   *   for the table.
   * @param Drupal\views\Plugin\views\join\JoinPluginBase $join
   *   A Join object (or derived object) to join the alias in.
   * @param $base
   *   The name of the 'base' table this relationship represents; this
   *   tells the join search which path to attempt to use when finding
   *   the path to this relationship.
   * @param $link_point
   *   If this relationship links to something other than the primary
   *   table, specify that table here. For example, a 'track' node
   *   might have a relationship to an 'album' node, which might
   *   have a relationship to an 'artist' node.
   */
  public function addRelationship($alias, JoinPluginBase $join, $base, $link_point = NULL) {
    if (empty($link_point)) {
      $link_point = $this->view->storage->get('base_table');
    }
    elseif (!array_key_exists($link_point, $this->relationships)) {
      return FALSE;
    }

    // Make sure $alias isn't already used; if it, start adding stuff.
    $alias_base = $alias;
    $count = 1;
    while (!empty($this->relationships[$alias])) {
      $alias = $alias_base . '_' . $count++;
    }

    // Make sure this join is adjusted for our relationship.
    if ($link_point && isset($this->relationships[$link_point])) {
      $join = $this->adjustJoin($join, $link_point);
    }

    // Add the table directly to the queue to avoid accidentally marking
    // it.
    $this->table_queue[$alias] = array(
      'table' => $join->table,
      'num' => 1,
      'alias' => $alias,
      'join' => $join,
      'relationship' => $link_point,
    );

    $this->relationships[$alias] = array(
      'link' => $link_point,
      'table' => $join->table,
      'base' => $base,
    );

    $this->tables[$this->view->storage->get('base_table')][$alias] = array(
      'count' => 1,
      'alias' => $alias,
    );

    return $alias;
  }

  /**
   * Add a table to the query, ensuring the path exists.
   *
   * This function will test to ensure that the path back to the primary
   * table is valid and exists; if you do not wish for this testing to
   * occur, use $query->queueTable() instead.
   *
   * @param $table
   *   The name of the table to add. It needs to exist in the global table
   *   array.
   * @param $relationship
   *   An alias of a table; if this is set, the path back to this table will
   *   be tested prior to adding the table, making sure that all intermediary
   *   tables exist and are properly aliased. If set to NULL the path to
   *   the primary table will be ensured. If the path cannot be made, the
   *   table will NOT be added.
   * @param Drupal\views\Plugin\views\join\JoinPluginBase $join
   *   In some join configurations this table may actually join back through
   *   a different method; this is most likely to be used when tracing
   *   a hierarchy path. (node->parent->parent2->parent3). This parameter
   *   will specify how this table joins if it is not the default.
   * @param $alias
   *   A specific alias to use, rather than the default alias.
   *
   * @return $alias
   *   The alias of the table; this alias can be used to access information
   *   about the table and should always be used to refer to the table when
   *   adding parts to the query. Or FALSE if the table was not able to be
   *   added.
   */
  public function addTable($table, $relationship = NULL, JoinPluginBase $join = NULL, $alias = NULL) {
    if (!$this->ensurePath($table, $relationship, $join)) {
      return FALSE;
    }

    if ($join && $relationship) {
      $join = $this->adjustJoin($join, $relationship);
    }

    return $this->queueTable($table, $relationship, $join, $alias);
  }

  /**
   * Add a table to the query without ensuring the path.
   *
   * This is a pretty internal function to Views and addTable() or
   * ensureTable() should be used instead of this one, unless you are
   * absolutely sure this is what you want.
   *
   * @param $table
   *   The name of the table to add. It needs to exist in the global table
   *   array.
   * @param $relationship
   *   The primary table alias this table is related to. If not set, the
   *   primary table will be used.
   * @param Drupal\views\Plugin\views\join\JoinPluginBase $join
   *   In some join configurations this table may actually join back through
   *   a different method; this is most likely to be used when tracing
   *   a hierarchy path. (node->parent->parent2->parent3). This parameter
   *   will specify how this table joins if it is not the default.
   * @param $alias
   *   A specific alias to use, rather than the default alias.
   *
   * @return $alias
   *   The alias of the table; this alias can be used to access information
   *   about the table and should always be used to refer to the table when
   *   adding parts to the query. Or FALSE if the table was not able to be
   *   added.
   */
  public function queueTable($table, $relationship = NULL, JoinPluginBase $join = NULL, $alias = NULL) {
    // If the alias is set, make sure it doesn't already exist.
    if (isset($this->table_queue[$alias])) {
      return $alias;
    }

    if (empty($relationship)) {
      $relationship = $this->view->storage->get('base_table');
    }

    if (!array_key_exists($relationship, $this->relationships)) {
      return FALSE;
    }

    if (!$alias && $join && $relationship && !empty($join->adjusted) && $table != $join->table) {
      if ($relationship == $this->view->storage->get('base_table')) {
        $alias = $table;
      }
      else {
        $alias = $relationship . '_' . $table;
      }
    }

    // Check this again to make sure we don't blow up existing aliases for already
    // adjusted joins.
    if (isset($this->table_queue[$alias])) {
      return $alias;
    }

    $alias = $this->markTable($table, $relationship, $alias);

    // If no alias is specified, give it the default.
    if (!isset($alias)) {
      $alias = $this->tables[$relationship][$table]['alias'] . $this->tables[$relationship][$table]['count'];
    }

    // If this is a relationship based table, add a marker with
    // the relationship as a primary table for the alias.
    if ($table != $alias) {
      $this->markTable($alias, $this->view->storage->get('base_table'), $alias);
    }

    // If no join is specified, pull it from the table data.
    if (!isset($join)) {
      $join = $this->getJoinData($table, $this->relationships[$relationship]['base']);
      if (empty($join)) {
        return FALSE;
      }

      $join = $this->adjustJoin($join, $relationship);
    }

    $this->table_queue[$alias] = array(
      'table' => $table,
      'num' => $this->tables[$relationship][$table]['count'],
      'alias' => $alias,
      'join' => $join,
      'relationship' => $relationship,
    );

    return $alias;
  }

  protected function markTable($table, $relationship, $alias) {
    // Mark that this table has been added.
    if (empty($this->tables[$relationship][$table])) {
      if (!isset($alias)) {
        $alias = '';
        if ($relationship != $this->view->storage->get('base_table')) {
          // double underscore will help prevent accidental name
          // space collisions.
          $alias = $relationship . '__';
        }
        $alias .= $table;
      }
      $this->tables[$relationship][$table] = array(
        'count' => 1,
        'alias' => $alias,
      );
    }
    else {
      $this->tables[$relationship][$table]['count']++;
    }

    return $alias;
  }

  /**
   * Ensure a table exists in the queue; if it already exists it won't
   * do anything, but if it doesn't it will add the table queue. It will ensure
   * a path leads back to the relationship table.
   *
   * @param $table
   *   The unaliased name of the table to ensure.
   * @param $relationship
   *   The relationship to ensure the table links to. Each relationship will
   *   get a unique instance of the table being added. If not specified,
   *   will be the primary table.
   * @param Drupal\views\Plugin\views\join\JoinPluginBase $join
   *   A Join object (or derived object) to join the alias in.
   *
   * @return
   *   The alias used to refer to this specific table, or NULL if the table
   *   cannot be ensured.
   */
  public function ensureTable($table, $relationship = NULL, JoinPluginBase $join = NULL) {
    // ensure a relationship
    if (empty($relationship)) {
      $relationship = $this->view->storage->get('base_table');
    }

    // If the relationship is the primary table, this actually be a relationship
    // link back from an alias. We store all aliases along with the primary table
    // to detect this state, because eventually it'll hit a table we already
    // have and that's when we want to stop.
    if ($relationship == $this->view->storage->get('base_table') && !empty($this->tables[$relationship][$table])) {
      return $this->tables[$relationship][$table]['alias'];
    }

    if (!array_key_exists($relationship, $this->relationships)) {
      return FALSE;
    }

    if ($table == $this->relationships[$relationship]['base']) {
      return $relationship;
    }

    // If we do not have join info, fetch it.
    if (!isset($join)) {
      $join = $this->getJoinData($table, $this->relationships[$relationship]['base']);
    }

    // If it can't be fetched, this won't work.
    if (empty($join)) {
      return;
    }

    // Adjust this join for the relationship, which will ensure that the 'base'
    // table it links to is correct. Tables adjoined to a relationship
    // join to a link point, not the base table.
    $join = $this->adjustJoin($join, $relationship);

    if ($this->ensurePath($table, $relationship, $join)) {
      // Attempt to eliminate redundant joins.  If this table's
      // relationship and join exactly matches an existing table's
      // relationship and join, we do not have to join to it again;
      // just return the existing table's alias.  See
      // http://groups.drupal.org/node/11288 for details.
      //
      // This can be done safely here but not lower down in
      // queueTable(), because queueTable() is also used by
      // addTable() which requires the ability to intentionally add
      // the same table with the same join multiple times.  For
      // example, a view that filters on 3 taxonomy terms using AND
      // needs to join taxonomy_term_data 3 times with the same join.

      // scan through the table queue to see if a matching join and
      // relationship exists.  If so, use it instead of this join.

      // TODO: Scanning through $this->table_queue results in an
      // O(N^2) algorithm, and this code runs every time the view is
      // instantiated (Views 2 does not currently cache queries).
      // There are a couple possible "improvements" but we should do
      // some performance testing before picking one.
      foreach ($this->table_queue as $queued_table) {
        // In PHP 4 and 5, the == operation returns TRUE for two objects
        // if they are instances of the same class and have the same
        // attributes and values.
        if ($queued_table['relationship'] == $relationship && $queued_table['join'] == $join) {
          return $queued_table['alias'];
        }
      }

      return $this->queueTable($table, $relationship, $join);
    }
  }

  /**
   * Make sure that the specified table can be properly linked to the primary
   * table in the JOINs. This function uses recursion. If the tables
   * needed to complete the path back to the primary table are not in the
   * query they will be added, but additional copies will NOT be added
   * if the table is already there.
   */
  protected function ensurePath($table, $relationship = NULL, $join = NULL, $traced = array(), $add = array()) {
    if (!isset($relationship)) {
      $relationship = $this->view->storage->get('base_table');
    }

    if (!array_key_exists($relationship, $this->relationships)) {
      return FALSE;
    }

    // If we do not have join info, fetch it.
    if (!isset($join)) {
      $join = $this->getJoinData($table, $this->relationships[$relationship]['base']);
    }

    // If it can't be fetched, this won't work.
    if (empty($join)) {
      return FALSE;
    }

    // Does a table along this path exist?
    if (isset($this->tables[$relationship][$table]) ||
      ($join && $join->leftTable == $relationship) ||
      ($join && $join->leftTable == $this->relationships[$relationship]['table'])) {

      // Make sure that we're linking to the correct table for our relationship.
      foreach (array_reverse($add) as $table => $path_join) {
        $this->queueTable($table, $relationship, $this->adjustJoin($path_join, $relationship));
      }
      return TRUE;
    }

    // Have we been this way?
    if (isset($traced[$join->leftTable])) {
      // we looped. Broked.
      return FALSE;
    }

    // Do we have to add this table?
    $left_join = $this->getJoinData($join->leftTable, $this->relationships[$relationship]['base']);
    if (!isset($this->tables[$relationship][$join->leftTable])) {
      $add[$join->leftTable] = $left_join;
    }

    // Keep looking.
    $traced[$join->leftTable] = TRUE;
    return $this->ensurePath($join->leftTable, $relationship, $left_join, $traced, $add);
  }

  /**
   * Fix a join to adhere to the proper relationship; the left table can vary
   * based upon what relationship items are joined in on.
   */
  protected function adjustJoin($join, $relationship) {
    if (!empty($join->adjusted)) {
      return $join;
    }

    if (empty($relationship) || empty($this->relationships[$relationship])) {
      return $join;
    }

    // Adjusts the left table for our relationship.
    if ($relationship != $this->view->storage->get('base_table')) {
      // If we're linking to the primary table, the relationship to use will
      // be the prior relationship. Unless it's a direct link.

      // Safety! Don't modify an original here.
      $join = clone $join;

      // Do we need to try to ensure a path?
      if ($join->leftTable != $this->relationships[$relationship]['table'] &&
        $join->leftTable != $this->relationships[$relationship]['base'] &&
        !isset($this->tables[$relationship][$join->leftTable]['alias'])) {
        $this->ensureTable($join->leftTable, $relationship);
      }

      // First, if this is our link point/anchor table, just use the relationship
      if ($join->leftTable == $this->relationships[$relationship]['table']) {
        $join->leftTable = $relationship;
      }
      // then, try the base alias.
      elseif (isset($this->tables[$relationship][$join->leftTable]['alias'])) {
        $join->leftTable = $this->tables[$relationship][$join->leftTable]['alias'];
      }
      // But if we're already looking at an alias, use that instead.
      elseif (isset($this->table_queue[$relationship]['alias'])) {
        $join->leftTable = $this->table_queue[$relationship]['alias'];
      }
    }

    $join->adjusted = TRUE;
    return $join;
  }

  /**
   * Retrieve join data from the larger join data cache.
   *
   * @param $table
   *   The table to get the join information for.
   * @param $base_table
   *   The path we're following to get this join.
   *
   * @return Drupal\views\Plugin\views\join\JoinPluginBase
   *   A Join object or child object, if one exists.
   */
  public function getJoinData($table, $base_table) {
    // Check to see if we're linking to a known alias. If so, get the real
    // table's data instead.
    if (!empty($this->table_queue[$table])) {
      $table = $this->table_queue[$table]['table'];
    }
    return HandlerBase::getTableJoin($table, $base_table);
  }

  /**
   * Get the information associated with a table.
   *
   * If you need the alias of a table with a particular relationship, use
   * ensureTable().
   */
  public function getTableInfo($table) {
    if (!empty($this->table_queue[$table])) {
      return $this->table_queue[$table];
    }

    // In rare cases we might *only* have aliased versions of the table.
    if (!empty($this->tables[$this->view->storage->get('base_table')][$table])) {
      $alias = $this->tables[$this->view->storage->get('base_table')][$table]['alias'];
      if (!empty($this->table_queue[$alias])) {
        return $this->table_queue[$alias];
      }
    }
  }

  /**
   * Add a field to the query table, possibly with an alias. This will
   * automatically call ensureTable to make sure the required table
   * exists, *unless* $table is unset.
   *
   * @param $table
   *   The table this field is attached to. If NULL, it is assumed this will
   *   be a formula; otherwise, ensureTable is used to make sure the
   *   table exists.
   * @param $field
   *   The name of the field to add. This may be a real field or a formula.
   * @param $alias
   *   The alias to create. If not specified, the alias will be $table_$field
   *   unless $table is NULL. When adding formulae, it is recommended that an
   *   alias be used.
   * @param $params
   *   An array of parameters additional to the field that will control items
   *   such as aggregation functions and DISTINCT.
   *
   * @return $name
   *   The name that this field can be referred to as. Usually this is the alias.
   */
  public function addField($table, $field, $alias = '', $params = array()) {
    // We check for this specifically because it gets a special alias.
    if ($table == $this->view->storage->get('base_table') && $field == $this->view->storage->get('base_field') && empty($alias)) {
      $alias = $this->view->storage->get('base_field');
    }

    if ($table && empty($this->table_queue[$table])) {
      $this->ensureTable($table);
    }

    if (!$alias && $table) {
      $alias = $table . '_' . $field;
    }

    // Make sure an alias is assigned
    $alias = $alias ? $alias : $field;

    // PostgreSQL truncates aliases to 63 characters: http://drupal.org/node/571548

    // We limit the length of the original alias up to 60 characters
    // to get a unique alias later if its have duplicates
    $alias = strtolower(substr($alias, 0, 60));

    // Create a field info array.
    $field_info = array(
      'field' => $field,
      'table' => $table,
      'alias' => $alias,
    ) + $params;

    // Test to see if the field is actually the same or not. Due to
    // differing parameters changing the aggregation function, we need
    // to do some automatic alias collision detection:
    $base = $alias;
    $counter = 0;
    while (!empty($this->fields[$alias]) && $this->fields[$alias] != $field_info) {
      $field_info['alias'] = $alias = $base . '_' . ++$counter;
    }

    if (empty($this->fields[$alias])) {
      $this->fields[$alias] = $field_info;
    }

    // Keep track of all aliases used.
    $this->field_aliases[$table][$field] = $alias;

    return $alias;
  }

  /**
   * Remove all fields that may've been added; primarily used for summary
   * mode where we're changing the query because we didn't get data we needed.
   */
  public function clearFields() {
    $this->fields = array();
  }

  /**
   * Add a simple WHERE clause to the query. The caller is responsible for
   * ensuring that all fields are fully qualified (TABLE.FIELD) and that
   * the table already exists in the query.
   *
   * @param $group
   *   The WHERE group to add these to; groups are used to create AND/OR
   *   sections. Groups cannot be nested. Use 0 as the default group.
   *   If the group does not yet exist it will be created as an AND group.
   * @param $field
   *   The name of the field to check.
   * @param $value
   *   The value to test the field against. In most cases, this is a scalar. For more
   *   complex options, it is an array. The meaning of each element in the array is
   *   dependent on the $operator.
   * @param $operator
   *   The comparison operator, such as =, <, or >=. It also accepts more complex
   *   options such as IN, LIKE, or BETWEEN. Defaults to IN if $value is an array
   *   = otherwise. If $field is a string you have to use 'formula' here.
   *
   * The $field, $value and $operator arguments can also be passed in with a
   * single DatabaseCondition object, like this:
   * @code
   *   $this->query->addWhere(
   *     $this->options['group'],
   *     db_or()
   *       ->condition($field, $value, 'NOT IN')
   *       ->condition($field, $value, 'IS NULL')
   *   );
   * @endcode
   *
   * @see Drupal\Core\Database\Query\ConditionInterface::condition()
   * @see Drupal\Core\Database\Query\Condition
   */
  public function addWhere($group, $field, $value = NULL, $operator = NULL) {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->setWhereGroup('AND', $group);
    }

    $this->where[$group]['conditions'][] = array(
      'field' => $field,
      'value' => $value,
      'operator' => $operator,
    );
  }

  /**
   * Add a complex WHERE clause to the query.
   *
   * The caller is reponsible for ensuring that all fields are fully qualified
   * (TABLE.FIELD) and that the table already exists in the query.
   * Internally the dbtng method "where" is used.
   *
   * @param $group
   *   The WHERE group to add these to; groups are used to create AND/OR
   *   sections. Groups cannot be nested. Use 0 as the default group.
   *   If the group does not yet exist it will be created as an AND group.
   * @param $snippet
   *   The snippet to check. This can be either a column or
   *   a complex expression like "UPPER(table.field) = 'value'"
   * @param $args
   *   An associative array of arguments.
   *
   * @see QueryConditionInterface::where()
   */
  public function addWhereExpression($group, $snippet, $args = array()) {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->setWhereGroup('AND', $group);
    }

    $this->where[$group]['conditions'][] = array(
      'field' => $snippet,
      'value' => $args,
      'operator' => 'formula',
    );
  }

  /**
   * Add a complex HAVING clause to the query.
   * The caller is responsible for ensuring that all fields are fully qualified
   * (TABLE.FIELD) and that the table and an appropriate GROUP BY already exist in the query.
   * Internally the dbtng method "having" is used.
   *
   * @param $group
   *   The HAVING group to add these to; groups are used to create AND/OR
   *   sections. Groups cannot be nested. Use 0 as the default group.
   *   If the group does not yet exist it will be created as an AND group.
   * @param $snippet
   *   The snippet to check. This can be either a column or
   *   a complex expression like "COUNT(table.field) > 3"
   * @param $args
   *   An associative array of arguments.
   *
   * @see QueryConditionInterface::having()
   */
  public function addHavingExpression($group, $snippet, $args = array()) {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->having[$group])) {
      $this->setWhereGroup('AND', $group, 'having');
    }

    // Add the clause and the args.
    $this->having[$group]['conditions'][] = array(
      'field' => $snippet,
      'value' => $args,
      'operator' => 'formula',
    );
  }

  /**
   * Add an ORDER BY clause to the query.
   *
   * @param $table
   *   The table this field is part of. If a formula, enter NULL.
   *   If you want to orderby random use "rand" as table and nothing else.
   * @param $field
   *   The field or formula to sort on. If already a field, enter NULL
   *   and put in the alias.
   * @param $order
   *   Either ASC or DESC.
   * @param $alias
   *   The alias to add the field as. In SQL, all fields in the order by
   *   must also be in the SELECT portion. If an $alias isn't specified
   *   one will be generated for from the $field; however, if the
   *   $field is a formula, this alias will likely fail.
   * @param $params
   *   Any params that should be passed through to the addField.
   */
  public function addOrderBy($table, $field = NULL, $order = 'ASC', $alias = '', $params = array()) {
    // Only ensure the table if it's not the special random key.
    // @todo: Maybe it would make sense to just add an addOrderByRand or something similar.
    if ($table && $table != 'rand') {
      $this->ensureTable($table);
    }

    // Only fill out this aliasing if there is a table;
    // otherwise we assume it is a formula.
    if (!$alias && $table) {
      $as = $table . '_' . $field;
    }
    else {
      $as = $alias;
    }

    if ($field) {
      $as = $this->addField($table, $field, $as, $params);
    }

    $this->orderby[] = array(
      'field' => $as,
      'direction' => strtoupper($order)
    );
  }

  /**
   * Add a simple GROUP BY clause to the query. The caller is responsible
   * for ensuring that the fields are fully qualified and the table is properly
   * added.
   */
  public function addGroupBy($clause) {
    // Only add it if it's not already in there.
    if (!in_array($clause, $this->groupby)) {
      $this->groupby[] = $clause;
    }
  }

  /**
   * Returns the alias for the given field added to $table.
   *
   * @access protected
   *
   * @see \Drupal\views\Plugin\views\query\Sql::addField
   */
  protected function getFieldAlias($table_alias, $field) {
    return isset($this->field_aliases[$table_alias][$field]) ? $this->field_aliases[$table_alias][$field] : FALSE;
  }

  /**
   * Adds a query tag to the sql object.
   *
   * @see SelectQuery::addTag()
   */
  public function addTag($tag) {
    $this->tags[] = $tag;
  }

  /**
   * Generates a unique placeholder used in the db query.
   */
  function placeholder($base = 'views') {
    static $placeholders = array();
    if (!isset($placeholders[$base])) {
      $placeholders[$base] = 0;
      return ':' . $base;
    }
    else {
      return ':' . $base . ++$placeholders[$base];
    }
  }

  /**
   * Construct the "WHERE" or "HAVING" part of the query.
   *
   * As views has to wrap the conditions from arguments with AND, a special
   * group is wrapped around all conditions. This special group has the ID 0.
   * There is other code in filters which makes sure that the group IDs are
   * higher than zero.
   *
   * @param $where
   *   'where' or 'having'.
   */
  protected function buildCondition($where = 'where') {
    $has_condition = FALSE;
    $has_arguments = FALSE;
    $has_filter = FALSE;

    $main_group = db_and();
    $filter_group = $this->group_operator == 'OR' ? db_or() : db_and();

    foreach ($this->$where as $group => $info) {

      if (!empty($info['conditions'])) {
        $sub_group = $info['type'] == 'OR' ? db_or() : db_and();
        foreach ($info['conditions'] as $key => $clause) {
          // DBTNG doesn't support to add the same subquery twice to the main
          // query and the count query, so clone the subquery to have two instances
          // of the same object. - http://drupal.org/node/1112854
          if (is_object($clause['value']) && $clause['value'] instanceof SelectQuery) {
            $clause['value'] = clone $clause['value'];
          }
          if ($clause['operator'] == 'formula') {
            $has_condition = TRUE;
            $sub_group->where($clause['field'], $clause['value']);
          }
          else {
            $has_condition = TRUE;
            $sub_group->condition($clause['field'], $clause['value'], $clause['operator']);
          }
        }

        // Add the item to the filter group.
        if ($group != 0) {
          $has_filter = TRUE;
          $filter_group->condition($sub_group);
        }
        else {
          $has_arguments = TRUE;
          $main_group->condition($sub_group);
        }
      }
    }

    if ($has_filter) {
      $main_group->condition($filter_group);
    }

    if (!$has_arguments && $has_condition) {
      return $filter_group;
    }
    if ($has_arguments && $has_condition) {
      return $main_group;
    }
  }

  /**
   * Returns a list of non-aggregates to be added to the "group by" clause.
   *
   * Non-aggregates are fields that have no aggregation function (count, sum,
   * etc) applied. Since the SQL standard requires all fields to either have
   * an aggregation function applied, or to be in the GROUP BY clause, Views
   * gathers those fields and adds them to the GROUP BY clause.
   *
   * @return array
   *   An array of the fieldnames which are non-aggregates.
   */
  protected function getNonAggregates() {
    $non_aggregates = array();
    foreach ($this->fields as $field) {
      $string = '';
      if (!empty($field['table'])) {
        $string .= $field['table'] . '.';
      }
      $string .= $field['field'];
      $fieldname = (!empty($field['alias']) ? $field['alias'] : $string);

      if (!empty($field['count'])) {
        // Retained for compatibility.
        $field['function'] = 'count';
      }

      if (!empty($field['function'])) {
        $this->has_aggregate = TRUE;
      }
      // This is a formula, using no tables.
      elseif (empty($field['table'])) {
        $non_aggregates[] = $fieldname;
      }
      elseif (empty($field['aggregate'])) {
        $non_aggregates[] = $fieldname;
      }

      if ($this->get_count_optimized) {
        // We only want the first field in this case.
        break;
      }
    }

    return $non_aggregates;
  }

  /**
   * Adds fields to the query.
   *
   * @param Drupal\Core\Database\Query\SelectInterface $query
   *   The drupal query object.
   */
  protected function compileFields($query) {
    foreach ($this->fields as $field) {
      $string = '';
      if (!empty($field['table'])) {
        $string .= $field['table'] . '.';
      }
      $string .= $field['field'];
      $fieldname = (!empty($field['alias']) ? $field['alias'] : $string);

      if (!empty($field['count'])) {
        // Retained for compatibility.
        $field['function'] = 'count';
      }

      if (!empty($field['function'])) {
        $info = $this->getAggregationInfo();
        if (!empty($info[$field['function']]['method']) && is_callable(array($this, $info[$field['function']]['method']))) {
          $string = $this::$info[$field['function']]['method']($field['function'], $string);
          $placeholders = !empty($field['placeholders']) ? $field['placeholders'] : array();
          $query->addExpression($string, $fieldname, $placeholders);
        }

        $this->has_aggregate = TRUE;
      }
      // This is a formula, using no tables.
      elseif (empty($field['table'])) {
        $placeholders = !empty($field['placeholders']) ? $field['placeholders'] : array();
        $query->addExpression($string, $fieldname, $placeholders);
      }
      elseif ($this->distinct && !in_array($fieldname, $this->groupby)) {
        $query->addField(!empty($field['table']) ? $field['table'] : $this->view->storage->get('base_table'), $field['field'], $fieldname);
      }
      elseif (empty($field['aggregate'])) {
        $query->addField(!empty($field['table']) ? $field['table'] : $this->view->storage->get('base_table'), $field['field'], $fieldname);
      }

      if ($this->get_count_optimized) {
        // We only want the first field in this case.
        break;
      }
    }
  }

  /**
   * Generate a query and a countquery from all of the information supplied
   * to the object.
   *
   * @param $get_count
   *   Provide a countquery if this is true, otherwise provide a normal query.
   */
  public function query($get_count = FALSE) {
    // Check query distinct value.
    if (empty($this->no_distinct) && $this->distinct && !empty($this->fields)) {
      $base_field_alias = $this->addField($this->view->storage->get('base_table'), $this->view->storage->get('base_field'));
      $this->addGroupBy($base_field_alias);
      $distinct = TRUE;
    }

    /**
     * An optimized count query includes just the base field instead of all the fields.
     * Determine of this query qualifies by checking for a groupby or distinct.
     */
    if ($get_count && !$this->groupby) {
      foreach ($this->fields as $field) {
        if (!empty($field['distinct']) || !empty($field['function'])) {
          $this->get_count_optimized = FALSE;
          break;
        }
      }
    }
    else {
      $this->get_count_optimized = FALSE;
    }
    if (!isset($this->get_count_optimized)) {
      $this->get_count_optimized = TRUE;
    }

    $options = array();
    $target = 'default';
    $key = 'default';
    // Detect an external database and set the
    if (isset($this->view->base_database)) {
      $key = $this->view->base_database;
    }

    // Set the slave target if the slave option is set
    if (!empty($this->options['slave'])) {
      $target = 'slave';
    }

    // Go ahead and build the query.
    // db_select doesn't support to specify the key, so use getConnection directly.
    $query = Database::getConnection($target, $key)
      ->select($this->view->storage->get('base_table'), $this->view->storage->get('base_table'), $options)
      ->addTag('views')
      ->addTag('views_' . $this->view->storage->id());

    // Add the tags added to the view itself.
    foreach ($this->tags as $tag) {
      $query->addTag($tag);
    }

    if (!empty($distinct)) {
      $query->distinct();
    }

    $joins = $where = $having = $orderby = $groupby = '';
    $fields = $distinct = array();

    // Add all the tables to the query via joins. We assume all LEFT joins.
    foreach ($this->table_queue as $table) {
      if (is_object($table['join'])) {
        $table['join']->buildJoin($query, $table, $this);
      }
    }

    // Assemble the groupby clause, if any.
    $this->has_aggregate = FALSE;
    $non_aggregates = $this->getNonAggregates();
    if (count($this->having)) {
      $this->has_aggregate = TRUE;
    }
    $groupby = array();
    if ($this->has_aggregate && (!empty($this->groupby) || !empty($non_aggregates))) {
      $groupby = array_unique(array_merge($this->groupby, $non_aggregates));
    }

    // Make sure each entity table has the base field added so that the
    // entities can be loaded.
    $entity_tables = $this->getEntityTables();
    if ($entity_tables) {
      $params = array();
      if ($groupby) {
        // Handle grouping, by retrieving the minimum entity_id.
        $params = array(
          'function' => 'min',
        );
      }

      foreach ($entity_tables as $table_alias => $table) {
        $info = entity_get_info($table['entity_type']);
        $base_field = empty($table['revision']) ? $info['entity_keys']['id'] : $info['entity_keys']['revision'];
        $this->addField($table_alias, $base_field, '', $params);
      }
    }

    // Add all fields to the query.
    $this->compileFields($query);

    // Add groupby.
    if ($groupby) {
      foreach ($groupby as $field) {
        $query->groupBy($field);
      }
      if (!empty($this->having) && $condition = $this->buildCondition('having')) {
        $query->havingCondition($condition);
      }
    }

    if (!$this->get_count_optimized) {
      // we only add the orderby if we're not counting.
      if ($this->orderby) {
        foreach ($this->orderby as $order) {
          if ($order['field'] == 'rand_') {
            $query->orderRandom();
          }
          else {
            $query->orderBy($order['field'], $order['direction']);
          }
        }
      }
    }

    if (!empty($this->where) && $condition = $this->buildCondition('where')) {
      $query->condition($condition);
    }

    // Add a query comment.
    if (!empty($this->options['query_comment'])) {
      $query->comment($this->options['query_comment']);
    }

    // Add the query tags.
    if (!empty($this->options['query_tags'])) {
      foreach ($this->options['query_tags'] as $tag) {
        $query->addTag($tag);
      }
    }

    // Add all query substitutions as metadata.
    $query->addMetaData('views_substitutions', \Drupal::moduleHandler()->invokeAll('views_query_substitutions', array($this->view)));

    return $query;
  }

  /**
   * Get the arguments attached to the WHERE and HAVING clauses of this query.
   */
  public function getWhereArgs() {
    $args = array();
    foreach ($this->where as $group => $where) {
      $args = array_merge($args, $where['args']);
    }
    foreach ($this->having as $group => $having) {
      $args = array_merge($args, $having['args']);
    }
    return $args;
  }

  /**
   * Let modules modify the query just prior to finalizing it.
   */
  function alter(ViewExecutable $view) {
    \Drupal::moduleHandler()->invokeAll('views_query_alter', array($view, $this));
  }

  /**
   * Builds the necessary info to execute the query.
   */
  function build(ViewExecutable $view) {
    // Make the query distinct if the option was set.
    if (!empty($this->options['distinct'])) {
      $this->setDistinct(TRUE);
    }

    // Store the view in the object to be able to use it later.
    $this->view = $view;

    $view->initPager();

    // Let the pager modify the query to add limits.
    $view->pager->query();

    $view->build_info['query'] = $this->query();
    $view->build_info['count_query'] = $this->query(TRUE);
  }

  /**
   * Executes the query and fills the associated view object with according
   * values.
   *
   * Values to set: $view->result, $view->total_rows, $view->execute_time,
   * $view->current_page.
   */
  function execute(ViewExecutable $view) {
    $external = FALSE; // Whether this query will run against an external database.
    $query = $view->build_info['query'];
    $count_query = $view->build_info['count_query'];

    $query->addMetaData('view', $view);
    $count_query->addMetaData('view', $view);

    if (empty($this->options['disable_sql_rewrite'])) {
      $base_table_data = Views::viewsData()->get($this->view->storage->get('base_table'));
      if (isset($base_table_data['table']['base']['access query tag'])) {
        $access_tag = $base_table_data['table']['base']['access query tag'];
        $query->addTag($access_tag);
        $count_query->addTag($access_tag);
      }
    }

    $items = array();
    if ($query) {
      $additional_arguments = \Drupal::moduleHandler()->invokeAll('views_query_substitutions', array($view));

      // Count queries must be run through the preExecute() method.
      // If not, then hook_query_node_access_alter() may munge the count by
      // adding a distinct against an empty query string
      // (e.g. COUNT DISTINCT(1) ...) and no pager will return.
      // See pager.inc > PagerDefault::execute()
      // http://api.drupal.org/api/drupal/includes--pager.inc/function/PagerDefault::execute/7
      // See http://drupal.org/node/1046170.
      $count_query->preExecute();

      // Build the count query.
      $count_query = $count_query->countQuery();

      // Add additional arguments as a fake condition.
      // XXX: this doesn't work... because PDO mandates that all bound arguments
      // are used on the query. TODO: Find a better way to do this.
      if (!empty($additional_arguments)) {
        // $query->where('1 = 1', $additional_arguments);
        // $count_query->where('1 = 1', $additional_arguments);
      }

      $start = microtime(TRUE);

      try {
        if ($view->pager->useCountQuery() || !empty($view->get_total_rows)) {
          $view->pager->executeCountQuery($count_query);
        }

        // Let the pager modify the query to add limits.
        $view->pager->preExecute($query);

        if (!empty($this->limit) || !empty($this->offset)) {
          // We can't have an offset without a limit, so provide a very large limit instead.
          $limit  = intval(!empty($this->limit) ? $this->limit : 999999);
          $offset = intval(!empty($this->offset) ? $this->offset : 0);
          $query->range($offset, $limit);
        }

        $result = $query->execute();
        $result->setFetchMode(\PDO::FETCH_CLASS, 'Drupal\views\ResultRow');

        $view->result = iterator_to_array($result);

        $view->pager->postExecute($view->result);
        $view->pager->updatePageInfo();
        $view->total_rows = $view->pager->getTotalItems();

        // Load all entities contained in the results.
        $this->loadEntities($view->result);
      }
      catch (DatabaseExceptionWrapper $e) {
        $view->result = array();
        if (!empty($view->live_preview)) {
          drupal_set_message($e->getMessage(), 'error');
        }
        else {
          throw new DatabaseExceptionWrapper(format_string('Exception in @label[@view_name]: @message', array('@label' => $view->storage->label(), '@view_name' => $view->storage->id(), '@message' => $e->getMessage())));
        }
      }

    }
    else {
      $start = microtime(TRUE);
    }
    $view->execute_time = microtime(TRUE) - $start;
  }

  /**
   * Returns an array of all tables from the query that map to an entity type.
   *
   * Includes the base table and all relationships, if eligible.
   * Available keys for each table:
   * - base: The actual base table (i.e. "user" for an author relationship).
   * - relationship_id: The id of the relationship, or "none".
   * - entity_type: The entity type matching the base table.
   * - revision: A boolean that specifies whether the table is a base table or
   *   a revision table of the entity type.
   *
   * @return array
   *   An array of table information, keyed by table alias.
   */
  public function getEntityTables() {
    // Start with the base table.
    $entity_tables = array();
    $views_data = Views::viewsData();
    $base_table_data = $views_data->get($this->view->storage->get('base_table'));
    if (isset($base_table_data['table']['entity type'])) {
      $entity_tables[$this->view->storage->get('base_table')] = array(
        'base' => $this->view->storage->get('base_table'),
        'relationship_id' => 'none',
        'entity_type' => $base_table_data['table']['entity type'],
        'revision' => FALSE,
      );
    }
    // Include all relationships.
    foreach ($this->view->relationship as $relationship_id => $relationship) {
      $table_data = $views_data->get($relationship->definition['base']);
      if (isset($table_data['table']['entity type'])) {
        $entity_tables[$relationship->alias] = array(
          'base' => $relationship->definition['base'],
          'relationship_id' => $relationship_id,
          'entity_type' => $table_data['table']['entity type'],
          'revision' => FALSE,
        );
      }
    }

    // Determine which of the tables are revision tables.
    foreach ($entity_tables as $table_alias => $table) {
      $info = entity_get_info($table['entity_type']);
      if (isset($info['revision table']) && $info['revision table'] == $table['base']) {
        $entity_tables[$table_alias]['revision'] = TRUE;
      }
    }

    return $entity_tables;
  }

  /**
   * Loads all entities contained in the passed-in $results.
   *.
   * If the entity belongs to the base table, then it gets stored in
   * $result->_entity. Otherwise, it gets stored in
   * $result->_relationship_entities[$relationship_id];
   */
  function loadEntities(&$results) {
    $entity_tables = $this->getEntityTables();
    // No entity tables found, nothing else to do here.
    if (empty($entity_tables)) {
      return;
    }

    // Assemble a list of entities to load.
    $ids_by_table = array();
    foreach ($entity_tables as $table_alias => $table) {
      $entity_type = $table['entity_type'];
      $info = entity_get_info($entity_type);
      $id_key = empty($table['revision']) ? $info['entity_keys']['id'] : $info['entity_keys']['revision'];
      $id_alias = $this->getFieldAlias($table_alias, $id_key);

      foreach ($results as $index => $result) {
        // Store the entity id if it was found.
        if (isset($result->{$id_alias}) && $result->{$id_alias} != '') {
          $ids_by_table[$table_alias][$index] = $result->$id_alias;
        }
      }
    }

    // Load all entities and assign them to the correct result row.
    foreach ($ids_by_table as $table_alias => $ids) {
      $table = $entity_tables[$table_alias];
      $entity_type = $table['entity_type'];
      $relationship_id = $table['relationship_id'];

      // Drupal core currently has no way to load multiple revisions. Sad.
      if ($table['revision']) {
        $entities = array();
        foreach ($ids as $index => $revision_id) {
          $entity = entity_revision_load($entity_type, $revision_id);
          if ($entity) {
            $entities[$revision_id] = $entity;
          }
        }
      }
      else {
        $entities = entity_load_multiple($entity_type, $ids);
      }

      foreach ($ids as $index => $id) {
        if (isset($entities[$id])) {
          $entity = $entities[$id];
        }
        else {
          $entity = NULL;
        }

        if ($relationship_id == 'none') {
          $results[$index]->_entity = $entity;
        }
        else {
          $results[$index]->_relationship_entities[$relationship_id] = $entity;
        }
      }
    }
  }

  public function addSignature(ViewExecutable $view) {
    $view->query->addField(NULL, "'" . $view->storage->id() . ':' . $view->current_display . "'", 'view_name');
  }

  public function getAggregationInfo() {
    // @todo -- need a way to get database specific and customized aggregation
    // functions into here.
    return array(
      'group' => array(
        'title' => t('Group results together'),
        'is aggregate' => FALSE,
      ),
      'count' => array(
        'title' => t('Count'),
        'method' => 'aggregationMethodSimple',
        'handler' => array(
          'argument' => 'groupby_numeric',
          'field' => 'numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ),
      ),
      'count_distinct' => array(
        'title' => t('Count DISTINCT'),
        'method' => 'aggregationMethodDistinct',
        'handler' => array(
          'argument' => 'groupby_numeric',
          'field' => 'numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ),
      ),
      'sum' => array(
        'title' => t('Sum'),
        'method' => 'aggregationMethodSimple',
        'handler' => array(
          'argument' => 'groupby_numeric',
          'field' => 'numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ),
      ),
      'avg' => array(
        'title' => t('Average'),
        'method' => 'aggregationMethodSimple',
        'handler' => array(
          'argument' => 'groupby_numeric',
          'field' => 'numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ),
      ),
      'min' => array(
        'title' => t('Minimum'),
        'method' => 'aggregationMethodSimple',
        'handler' => array(
          'argument' => 'groupby_numeric',
          'field' => 'numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ),
      ),
      'max' => array(
        'title' => t('Maximum'),
        'method' => 'aggregationMethodSimple',
        'handler' => array(
          'argument' => 'groupby_numeric',
          'field' => 'numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ),
      ),
      'stddev_pop' => array(
        'title' => t('Standard deviation'),
        'method' => 'aggregationMethodSimple',
        'handler' => array(
          'argument' => 'groupby_numeric',
          'field' => 'numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ),
      )
    );
  }

  public function aggregationMethodSimple($group_type, $field) {
    return strtoupper($group_type) . '(' . $field . ')';
  }

  public function aggregationMethodDistinct($group_type, $field) {
    $group_type = str_replace('_distinct', '', $group_type);
    return strtoupper($group_type) . '(DISTINCT ' . $field . ')';
  }

  /**
   * Overrides \Drupal\views\Plugin\views\query\QueryPluginBase::getDateField().
   */
  public function getDateField($field) {
    $db_type = Database::getConnection()->databaseType();
    $offset = $this->setupTimezone();
    if (isset($offset) && !is_numeric($offset)) {
      $dtz = new \DateTimeZone($offset);
      $dt = new \DateTime('now', $dtz);
      $offset_seconds = $dtz->getOffset($dt);
    }

    switch ($db_type) {
      case 'mysql':
        $field = "DATE_ADD('19700101', INTERVAL $field SECOND)";
        if (!empty($offset)) {
          $field = "($field + INTERVAL $offset_seconds SECOND)";
        }
        break;
      case 'pgsql':
        $field = "TO_TIMESTAMP($field)";
        if (!empty($offset)) {
          $field = "($field + INTERVAL '$offset_seconds SECONDS')";
        }
        break;
      case 'sqlite':
        if (!empty($offset)) {
          $field = "($field + '$offset_seconds')";
        }
        break;
    }

    return $field;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\query\QueryPluginBase::setupTimezone().
   */
  public function setupTimezone() {
    $timezone = drupal_get_user_timezone();

    // set up the database timezone
    $db_type = Database::getConnection()->databaseType();
    if (in_array($db_type, array('mysql', 'pgsql'))) {
      $offset = '+00:00';
      static $already_set = FALSE;
      if (!$already_set) {
        if ($db_type == 'pgsql') {
          Database::getConnection()->query("SET TIME ZONE INTERVAL '$offset' HOUR TO MINUTE");
        }
        elseif ($db_type == 'mysql') {
          Database::getConnection()->query("SET @@session.time_zone = '$offset'");
        }

        $already_set = TRUE;
      }
    }

    return $timezone;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\query\QueryPluginBase::getDateFormat().
   */
  public function getDateFormat($field, $format) {
    $db_type = Database::getConnection()->databaseType();
    switch ($db_type) {
      case 'mysql':
        $replace = array(
          'Y' => '%Y',
          'y' => '%y',
          'M' => '%b',
          'm' => '%m',
          'n' => '%c',
          'F' => '%M',
          'D' => '%a',
          'd' => '%d',
          'l' => '%W',
          'j' => '%e',
          'W' => '%v',
          'H' => '%H',
          'h' => '%h',
          'i' => '%i',
          's' => '%s',
          'A' => '%p',
        );
        $format = strtr($format, $replace);
        return "DATE_FORMAT($field, '$format')";
      case 'pgsql':
        $replace = array(
          'Y' => 'YYYY',
          'y' => 'YY',
          'M' => 'Mon',
          'm' => 'MM',
          // No format for Numeric representation of a month, without leading
          // zeros.
          'n' => 'MM',
          'F' => 'Month',
          'D' => 'Dy',
          'd' => 'DD',
          'l' => 'Day',
          // No format for Day of the month without leading zeros.
          'j' => 'DD',
          'W' => 'WW',
          'H' => 'HH24',
          'h' => 'HH12',
          'i' => 'MI',
          's' => 'SS',
          'A' => 'AM',
        );
        $format = strtr($format, $replace);
        return "TO_CHAR($field, '$format')";
      case 'sqlite':
        $replace = array(
          'Y' => '%Y',
          // No format for 2 digit year number.
          'y' => '%Y',
          // No format for 3 letter month name.
          'M' => '%m',
          'm' => '%m',
          // No format for month number without leading zeros.
          'n' => '%m',
          // No format for full month name.
          'F' => '%m',
          // No format for 3 letter day name.
          'D' => '%d',
          'd' => '%d',
          // No format for full day name.
          'l' => '%d',
          // no format for day of month number without leading zeros.
          'j' => '%d',
          'W' => '%W',
          'H' => '%H',
          // No format for 12 hour hour with leading zeros.
          'h' => '%H',
          'i' => '%M',
          's' => '%S',
          // No format for AM/PM.
          'A' => '',
        );
        $format = strtr($format, $replace);
        return "strftime('$format', $field, 'unixepoch')";
    }
  }

}
