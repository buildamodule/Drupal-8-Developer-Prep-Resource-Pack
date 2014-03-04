<?php

/**
 * @file
 * Contains \Drupal\filter\Plugin\Filter\FilterBase.
 */

namespace Drupal\filter\Plugin;

use Drupal\Component\Plugin\PluginBase;

/**
 * Provides a base class for Filter plugins.
 */
abstract class FilterBase extends PluginBase implements FilterInterface {

  /**
   * The plugin ID of this filter.
   *
   * @var string
   */
  protected $plugin_id;

  /**
   * The name of the module that owns this filter.
   *
   * @var string
   */
  public $module;

  /**
   * A Boolean indicating whether this filter is enabled.
   *
   * @var bool
   */
  public $status = FALSE;

  /**
   * The weight of this filter compared to others in a filter collection.
   *
   * @see FilterBase::$filterBag
   *
   * @var int
   */
  public $weight = 0;

  /**
   * A Boolean indicating whether the text processed by this filter may be cached.
   *
   * @var bool
   */
  public $cache = TRUE;

  /**
   * An associative array containing the configured settings of this filter.
   *
   * @var array
   */
  public $settings = array();

  /**
   * A collection of all filters this filter participates in.
   *
   * @var \Drupal\filter\FilterBag
   */
  protected $bag;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->module = $this->pluginDefinition['module'];
    $this->cache = $this->pluginDefinition['cache'];

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    if (isset($configuration['status'])) {
      $this->status = (bool) $configuration['status'];
    }
    if (isset($configuration['weight'])) {
      $this->weight = (int) $configuration['weight'];
    }
    if (isset($configuration['settings'])) {
      $this->settings = (array) $configuration['settings'];
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return array(
      'id' => $this->getPluginId(),
      'module' => $this->pluginDefinition['module'],
      'status' => $this->status,
      'weight' => $this->weight,
      'settings' => $this->settings,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->pluginDefinition['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, array &$form_state) {
    // Implementations should work with and return $form. Returning an empty
    // array here allows the text format administration form to identify whether
    // the filter plugin has any settings form elements.
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function prepare($text, $langcode, $cache, $cache_id) {
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function getHTMLRestrictions() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
  }

}
