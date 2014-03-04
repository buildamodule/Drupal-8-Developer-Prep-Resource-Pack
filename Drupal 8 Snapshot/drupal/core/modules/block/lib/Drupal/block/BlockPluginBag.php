<?php

/**
 * @file
 * Contains \Drupal\block\BlockPluginBag.
 */

namespace Drupal\block;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\String;
use Drupal\Component\Plugin\DefaultSinglePluginBag;

/**
 * Provides a collection of block plugins.
 */
class BlockPluginBag extends DefaultSinglePluginBag {

  /**
   * The block ID this plugin bag belongs to.
   *
   * @var string
   */
  protected $blockId;

  /**
   * {@inheritdoc}
   */
  public function __construct(PluginManagerInterface $manager, array $instance_ids, array $configuration, $block_id) {
    parent::__construct($manager, $instance_ids, $configuration);

    $this->blockId = $block_id;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\block\BlockPluginInterface
   */
  public function &get($instance_id) {
    return parent::get($instance_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    if (!$instance_id) {
      throw new PluginException(String::format("The block '@block' did not specify a plugin.", array('@block' => $this->blockId)));
    }

    try {
      parent::initializePlugin($instance_id);
    }
    catch (PluginException $e) {
      $module = $this->configuration['module'];
      // Ignore blocks belonging to disabled modules, but re-throw valid
      // exceptions when the module is enabled and the plugin is misconfigured.
      if (!$module || \Drupal::moduleHandler()->moduleExists($module)) {
        throw $e;
      }
    }
  }

}
