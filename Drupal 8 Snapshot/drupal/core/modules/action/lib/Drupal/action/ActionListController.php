<?php

/**
 * @file
 * Contains \Drupal\action\ActionListController.
 */

namespace Drupal\action;

use Drupal\Component\Utility\String;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListController;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use \Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\action\Form\ActionAdminManageForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Actions.
 */
class ActionListController extends ConfigEntityListController implements EntityControllerInterface {

  /**
   * @var bool
   */
  protected $hasConfigurableActions = FALSE;

  /**
   * The action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * Constructs a new ActionListController object.
   *
   * @param string $entity_type
   *   The entity type.
   * @param array $entity_info
   *   An array of entity info for the entity type.
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface $storage
   *   The action storage controller.
   * @param \Drupal\Core\Action\ActionManager $action_manager
   *   The action plugin manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke hooks on.
   */
  public function __construct($entity_type, array $entity_info, EntityStorageControllerInterface $storage, ActionManager $action_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($entity_type, $entity_info, $storage, $module_handler);

    $this->actionManager = $action_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $entity_type,
      $entity_info,
      $container->get('plugin.manager.entity')->getStorageController($entity_type),
      $container->get('plugin.manager.action'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = parent::load();
    foreach ($entities as $entity) {
      if ($entity->isConfigurable()) {
        $this->hasConfigurableActions = TRUE;
        continue;
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['type'] = $entity->getType();
    $row['label'] = String::checkPlain($entity->label());
    if ($this->hasConfigurableActions) {
      $row['operations']['data'] = $this->buildOperations($entity);
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = array(
      'type' => t('Action type'),
      'label' => t('Label'),
      'operations' => t('Operations'),
    );
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = $entity->isConfigurable() ? parent::getOperations($entity) : array();
    if (isset($operations['edit'])) {
      $operations['edit']['title'] = t('Configure');
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['action_header']['#markup'] = '<h3>' . t('Available actions:') . '</h3>';
    $build['action_table'] = parent::render();
    if (!$this->hasConfigurableActions) {
      unset($build['action_table']['#header']['operations']);
    }
    $build['action_admin_manage_form'] = drupal_get_form(new ActionAdminManageForm($this->actionManager));
    return $build;
  }

}
