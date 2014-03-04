<?php

/**
 * @file
 * Contains \Drupal\entity\Controller\EntityDisplayModeController.
 */

namespace Drupal\entity\Controller;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Controller\ControllerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides methods for entity display mode routes.
 */
class EntityDisplayModeController implements ControllerInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a new EntityDisplayModeFormBase.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(PluginManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.entity')
    );
  }

  /**
   * Provides a list of eligible entity types for adding view modes.
   *
   * @return array
   *   A list of entity types to add a view mode for.
   */
  public function viewModeTypeSelection() {
    drupal_set_title(t('Choose view mode entity type'));
    $entity_types = array();
    foreach ($this->entityManager->getDefinitions() as $entity_type => $entity_info) {
      if ($entity_info['fieldable'] && isset($entity_info['controllers']['render'])) {
        $entity_types[$entity_type] = array(
          'title' => $entity_info['label'],
          'href' => 'admin/structure/display-modes/view/add/' . $entity_type,
          'localized_options' => array(),
        );
      }
    }
    return array(
      '#theme' => 'admin_block_content',
      '#content' => $entity_types,
    );
  }

  /**
   * Provides a list of eligible entity types for adding form modes.
   *
   * @return array
   *   A list of entity types to add a form mode for.
   */
  public function formModeTypeSelection() {
    drupal_set_title(t('Choose form mode entity type'));
    $entity_types = array();
    foreach ($this->entityManager->getDefinitions() as $entity_type => $entity_info) {
      if ($entity_info['fieldable'] && isset($entity_info['controllers']['form'])) {
        $entity_types[$entity_type] = array(
          'title' => $entity_info['label'],
          'href' => 'admin/structure/display-modes/form/add/' . $entity_type,
          'localized_options' => array(),
        );
      }
    }
    return array(
      '#theme' => 'admin_block_content',
      '#content' => $entity_types,
    );
  }

}
