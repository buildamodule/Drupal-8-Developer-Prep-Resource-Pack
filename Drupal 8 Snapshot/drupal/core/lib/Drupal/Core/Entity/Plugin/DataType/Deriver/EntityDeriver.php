<?php

/**
 * @file
 * Contains \Drupal\Core\Entity\Plugin\DataType\Deriver\EntityDeriver.
 */

namespace Drupal\Core\Entity\Plugin\DataType\Deriver;

use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides data type plugins for each existing entity type and bundle.
 */
class EntityDeriver implements ContainerDerivativeInterface {

  /**
   * List of derivative definitions.
   *
   * @var array
   */
  protected $derivatives = array();

  /**
   * The base plugin ID this derivative is for.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * Constructs an EntityDeriver object.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   */
  public function __construct($base_plugin_id, EntityManager $entity_manager) {
    $this->basePluginId = $base_plugin_id;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('plugin.manager.entity')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, array $base_plugin_definition) {
    if (!empty($this->derivatives) && !empty($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
    $this->getDerivativeDefinitions($base_plugin_definition);
    if (isset($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions(array $base_plugin_definition) {
    // Also keep the 'entity' defined as is.
    $this->derivatives[''] = $base_plugin_definition;
    // Add definitions for each entity type and bundle.
    foreach ($this->entityManager->getDefinitions() as $entity_type => $info) {
      $this->derivatives[$entity_type] = array(
        'label' => $info['label'],
        'class' => $info['class'],
        'constraints' => array('EntityType' => $entity_type),
      ) + $base_plugin_definition;

      // Incorporate the bundles as entity:$entity_type:$bundle, if any.
      foreach (entity_get_bundles($entity_type) as $bundle => $bundle_info) {
        if ($bundle !== $entity_type) {
          $this->derivatives[$entity_type . ':' . $bundle] = array(
            'label' => $bundle_info['label'],
            'class' => $info['class'],
            'constraints' => array(
              'EntityType' => $entity_type,
              'Bundle' => $bundle,
            ),
          ) + $base_plugin_definition;
        }
      }
    }
    return $this->derivatives;
  }
}
