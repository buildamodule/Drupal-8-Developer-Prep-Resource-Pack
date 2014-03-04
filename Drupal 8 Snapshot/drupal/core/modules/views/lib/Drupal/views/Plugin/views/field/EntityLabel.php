<?php

/**
 * @file
 * Contains \Drupal\views\Plugin\views\field\EntityLabel.
 */

namespace Drupal\views\Plugin\views\field;

use Drupal\Component\Annotation\PluginID;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\Core\Entity\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display entity label optionally linked to entity page.
 *
 * @PluginID("entity_label")
 */
class EntityLabel extends FieldPluginBase {

  /**
   * Array of entities that reference to file.
   *
   * @var array
   */
  protected $loadedReferencers = array();

  /**
   * EntityManager class.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * Constructs a EntityLabel object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManager  $manager
   *   EntityManager that is stored internally and used to load nodes.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityManager $manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->additional_fields[$this->definition['entity type field']] = $this->definition['entity type field'];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['link_to_entity'] = array('default' => FALSE);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, &$form_state) {
    $form['link_to_entity'] = array(
      '#title' => t('Link to entity'),
      '#description' => t('Make entity label a link to entity page.'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->options['link_to_entity']),
    );
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->loadedReferencers[$this->getValue($values, $this->definition['entity type field'])][$this->getValue($values)];

    if (empty($entity)) {
      return NULL;
    }

    if (!empty($this->options['link_to_entity'])) {
      $uri = $entity->uri();
      $this->options['alter']['make_link'] = TRUE;
      $this->options['alter']['path'] = $uri['path'];
    }

    return $this->sanitizeValue($entity->label());
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    parent::preRender($values);

    $entity_ids_per_type = array();
    foreach ($values as $value) {
      $entity_ids_per_type[$this->getValue($value, 'type')][] = $this->getValue($value);
    }

    foreach ($entity_ids_per_type as $type => $ids) {
      $this->loadedReferencers[$type] = $this->entityManager->getStorageController($type)->loadMultiple($ids);
    }
  }

}
