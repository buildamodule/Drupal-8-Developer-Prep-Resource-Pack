<?php

/**
 * @file
 * Contains \Drupal\user\Plugin\views\argument\RolesRid.
 */

namespace Drupal\user\Plugin\views\argument;

use Drupal\Component\Annotation\PluginID;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityManager;
use Drupal\views\Plugin\views\argument\ManyToOne;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allow role ID(s) as argument.
 *
 * @ingroup views_argument_handlers
 *
 * @PluginID("users_roles_rid")
 */
class RolesRid extends ManyToOne {

  /**
   * The role entity storage controller
   *
   * @var \Drupal\user\RoleStorageController
   */
  protected $roleStorageController;

  /**
   * Constructs a \Drupal\user\Plugin\views\argument\RolesRid object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityManager $entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->roleStorageController = $entity_manager->getStorageController('user_role');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return parent::create($container, $configuration, $plugin_id, $plugin_definition, $container->get('plugin.manager.entity'));
  }

  /**
   * {@inheritdoc}
   */
  public function title_query() {
    $entities = $this->roleStorageController->loadMultiple($this->value);
    $titles = array();
    foreach ($entities as $entity) {
      $titles[] = String::checkPlain($entity->label());
    }
    return $titles;
  }

}
