<?php

/**
 * @file
 * Definition of Drupal\comment\Plugin\views\argument\UserUid.
 */

namespace Drupal\comment\Plugin\views\argument;

use Drupal\Component\Annotation\PluginID;
use Drupal\Core\Database\Connection;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler to accept a user id to check for nodes that
 * user posted or commented on.
 *
 * @ingroup views_argument_handlers
 *
 * @PluginID("argument_comment_user_uid")
 */
class UserUid extends ArgumentPluginBase {

  /**
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   Database Service Object.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('database'));
  }

  function title() {
    if (!$this->argument) {
      $title = \Drupal::config('user.settings')->get('anonymous');
    }
    else {
      $title = $this->database->query('SELECT u.name FROM {users} u WHERE u.uid = :uid', array(':uid' => $this->argument))->fetchField();
    }
    if (empty($title)) {
      return t('No user');
    }

    return check_plain($title);
  }

  protected function defaultActions($which = NULL) {
    // Disallow summary views on this argument.
    if (!$which) {
      $actions = parent::defaultActions();
      unset($actions['summary asc']);
      unset($actions['summary desc']);
      return $actions;
    }

    if ($which != 'summary asc' && $which != 'summary desc') {
      return parent::defaultActions($which);
    }
  }

  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    $subselect = $this->database->select('comment', 'c');
    $subselect->addField('c', 'cid');
    $subselect->condition('c.uid', $this->argument);
    $subselect->where("c.nid = $this->tableAlias.nid");

    $condition = db_or()
      ->condition("$this->tableAlias.uid", $this->argument, '=')
      ->exists($subselect);

    $this->query->addWhere(0, $condition);
  }

  /**
   * {@inheritdoc}
   */
  public function getSortName() {
    return t('Numerical', array(), array('context' => 'Sort order'));
  }

}
