<?php

/**
 * @file
 * Definition of Drupal\node\Plugin\views\argument\Type.
 */

namespace Drupal\node\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\String;
use Drupal\Component\Annotation\PluginID;

/**
 * Argument handler to accept a node type.
 *
 * @PluginID("node_type")
 */
class Type extends String {

  /**
   * Override the behavior of summaryName(). Get the user friendly version
   * of the node type.
   */
  public function summaryName($data) {
    return $this->node_type($data->{$this->name_alias});
  }

  /**
   * Override the behavior of title(). Get the user friendly version of the
   * node type.
   */
  function title() {
    return $this->node_type($this->argument);
  }

  function node_type($type_name) {
    $type = entity_load('node_type', $type_name);
    $output = $type ? $type->label() : t('Unknown content type');
    return check_plain($output);
  }

}
