<?php

/**
 * @file
 * Contains \Drupal\views\DisplayBag.
 */

namespace Drupal\views;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Plugin\DefaultPluginBag;

/**
 * A class which wraps the displays of a view so you can lazy-initialize them.
 */
class DisplayBag extends DefaultPluginBag {

  /**
   * Stores a reference to the view which has this displays attached.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * {@inheritdoc}
   */
  protected $pluginKey = 'display_plugin';

  /**
   * Constructs a DisplayBag object.
   *
   * @param \Drupal\views\ViewExecutable
   *   The view which has this displays attached.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The manager to be used for instantiating plugins.
   */
  public function __construct(ViewExecutable $view, PluginManagerInterface $manager) {
    parent::__construct($manager, $view->storage->get('display'));

    $this->view = $view;
    $this->initializePlugin('default');
  }

  /**
   * Destructs a DisplayBag object.
   */
  public function __destruct() {
    $this->clear();
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\views\Plugin\views\display\DisplayPluginBase
   */
  public function &get($instance_id) {
    return parent::get($instance_id);
  }

  /**
   * Overrides \Drupal\Component\Plugin\PluginBag::clear().
   */
  public function clear() {
    foreach (array_filter($this->pluginInstances) as $display_id => $display) {
      $display->destroy();
    }

    parent::clear();
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($display_id) {
    // Retrieve and initialize the new display handler with data.
    $display = &$this->view->storage->getDisplay($display_id);

    try {
      $this->configurations[$display_id] = $display;
      parent::initializePlugin($display_id);
    }
    // Catch any plugin exceptions that are thrown. So we can fail nicely if a
    // display plugin isn't found.
    catch (PluginException $e) {
      $message = $e->getMessage();
      drupal_set_message(t('!message', array('!message' => $message)), 'warning');
    }

    // If no plugin instance has been created, return NULL.
    if (empty($this->pluginInstances[$display_id])) {
      return NULL;
    }

    $this->pluginInstances[$display_id]->initDisplay($this->view, $display);
    // If this is not the default display handler, let it know which is since
    // it may well utilize some data from the default.
    if ($display_id != 'default') {
      $this->pluginInstances[$display_id]->default_display = $this->pluginInstances['default'];
    }
  }

  /**
   * Overrides \Drupal\Component\Plugin\PluginBag::remove().
   */
  public function remove($instance_id) {
    $this->get($instance_id)->remove();

    parent::remove($instance_id);
  }


}
