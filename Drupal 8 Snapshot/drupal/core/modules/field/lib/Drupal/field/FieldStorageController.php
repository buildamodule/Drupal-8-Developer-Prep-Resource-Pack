<?php

/**
 * @file
 * Contains \Drupal\field\FieldStorageController.
 */

namespace Drupal\field;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\Entity\ConfigStorageController;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;

/**
 * Controller class for fields.
 */
class FieldStorageController extends ConfigStorageController {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $state;

  /**
   * Constructs a FieldStorageController object.
   *
   * @param string $entity_type
   *   The entity type for which the instance is created.
   * @param array $entity_info
   *   An array of entity info for the entity type.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage service.
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreInterface $state
   *   The state key value store.
   */
  public function __construct($entity_type, array $entity_info, ConfigFactory $config_factory, StorageInterface $config_storage, QueryFactory $entity_query_factory, EntityManager $entity_manager, ModuleHandler $module_handler, KeyValueStoreInterface $state) {
    parent::__construct($entity_type, $entity_info, $config_factory, $config_storage, $entity_query_factory);

    $this->entityManager = $entity_manager;
    $this->moduleHandler = $module_handler;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $entity_type,
      $entity_info,
      $container->get('config.factory'),
      $container->get('config.storage'),
      $container->get('entity.query'),
      $container->get('plugin.manager.entity'),
      $container->get('module_handler'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $conditions = array()) {
    // Include instances of inactive fields if specified in the
    // $conditions parameters.
    $include_inactive = $conditions['include_inactive'];
    unset($conditions['include_inactive']);
    // Include deleted instances if specified in the $conditions parameters.
    $include_deleted = $conditions['include_deleted'];
    unset($conditions['include_deleted']);

    // Get fields stored in configuration.
    if (isset($conditions['field_name'])) {
      // Optimize for the most frequent case where we do have a specific ID.
      $fields = $this->entityManager->getStorageController($this->entityType)->loadMultiple(array($conditions['field_name']));
    }
    else {
      // No specific ID, we need to examine all existing fields.
      $fields = $this->entityManager->getStorageController($this->entityType)->loadMultiple();
    }

    // Merge deleted fields (stored in state) if needed.
    if ($include_deleted) {
      $deleted_fields = $this->state->get('field.field.deleted') ?: array();
      foreach ($deleted_fields as $id => $config) {
        $fields[$id] = $this->entityManager->getStorageController($this->entityType)->create($config);
      }
    }

    // Translate "do not include inactive instances" into actual conditions.
    if (!$include_inactive) {
      $conditions['active'] = TRUE;
      $conditions['storage.active'] = TRUE;
    }

    // Collect matching fields.
    $matching_fields = array();
    foreach ($fields as $field) {
      foreach ($conditions as $key => $value) {
        // Extract the actual value against which the condition is checked.
        switch ($key) {
          case 'storage.active':
            $checked_value = $field->storage['active'];
            break;

          case 'field_name';
            $checked_value = $field->id;
            break;

          default:
            $checked_value = $field->$key;
            break;
        }

        // Skip to the next field as soon as one condition does not match.
        if ($checked_value != $value) {
          continue 2;
        }
      }

      $this->moduleHandler->invokeAll('field_read_field', $field);

      // When returning deleted fields, key the results by UUID since they can
      // include several fields with the same ID.
      $key = $include_deleted ? $field->uuid : $field->id;
      $matching_fields[$key] = $field;
    }

    return $matching_fields;

  }
}
