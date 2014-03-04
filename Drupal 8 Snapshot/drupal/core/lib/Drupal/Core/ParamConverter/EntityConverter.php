<?php

/**
 * @file
 * Contains Drupal\Core\ParamConverter\EntityConverter.
 */

namespace Drupal\Core\ParamConverter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Drupal\Core\Entity\EntityManager;

/**
 * Parameter converter for upcasting entity ids to full objects.
 */
class EntityConverter implements ParamConverterInterface {

  /**
   * Entity manager which performs the upcasting in the end.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * Constructs a new EntityConverter.
   *
   * @param \Drupal\Core\Entity\EntityManager $entityManager
   *   The entity manager.
   */
  public function __construct(EntityManager $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults, Request $request) {
    $entity_type = substr($definition['type'], strlen('entity:'));
    if ($storage = $this->entityManager->getStorageController($entity_type)) {
      return $storage->load($value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    if (!empty($definition['type']) && strpos($definition['type'], 'entity:') === 0) {
      $entity_type = substr($definition['type'], strlen('entity:'));
      return (bool) $this->entityManager->getDefinition($entity_type);
    }
    return FALSE;
  }

}
