<?php

/**
 * @file
 * Contains \Drupal\aggregator\Plugin\views\argument\Fid.
 */

namespace Drupal\aggregator\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\Numeric;
use Drupal\Component\Annotation\PluginID;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler to accept an aggregator feed id.
 *
 * @ingroup views_argument_handlers
 *
 * @PluginID("aggregator_fid")
 */
class Fid extends Numeric {

  /**
   * The entity manager service
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
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
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('plugin.manager.entity'));
  }

  /**
   * {@inheritdoc}
   */
  function titleQuery() {
    $titles = array();

    $feeds = $this->entityManager->getStorageController('aggregator_feed')->loadMultiple($this->value);
    foreach ($feeds as $feed) {
      $titles[] = String::checkPlain($feed->label());
    }
    return $titles;
  }

}
