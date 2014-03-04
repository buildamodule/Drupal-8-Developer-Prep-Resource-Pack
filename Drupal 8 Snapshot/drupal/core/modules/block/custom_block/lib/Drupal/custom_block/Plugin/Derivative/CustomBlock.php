<?php

/**
 * @file
 * Contains \Drupal\custom_block\Plugin\Derivative\CustomBlock.
 */

namespace Drupal\custom_block\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DerivativeBase;

/**
 * Retrieves block plugin definitions for all custom blocks.
 */
class CustomBlock extends DerivativeBase {
  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions(array $base_plugin_definition) {
    $custom_blocks = entity_load_multiple('custom_block');
    foreach ($custom_blocks as $custom_block) {
      $this->derivatives[$custom_block->uuid->value] = $base_plugin_definition;
      $this->derivatives[$custom_block->uuid->value]['admin_label'] = $custom_block->info->value;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }
}
