<?php

/**
 * @file
 * Contains \Drupal\block\Plugin\Core\Entity\BlockInterface.
 */

namespace Drupal\block;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a block entity.
 */
interface BlockInterface extends ConfigEntityInterface {

  /**
   * Indicates the block label (title) should be displayed to end users.
   */
  const BLOCK_LABEL_VISIBLE = 'visible';

  /**
   * Returns the plugin instance.
   *
   * @return \Drupal\block\BlockPluginInterface
   *   The plugin instance for this block.
   */
  public function getPlugin();

}
