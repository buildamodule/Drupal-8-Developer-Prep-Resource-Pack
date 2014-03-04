<?php

/**
 * @file
 * Definition of Drupal\node\Plugins\views\filter\Status.
 */

namespace Drupal\node\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\Component\Annotation\PluginID;

/**
 * Filter by published status.
 *
 * @ingroup views_filter_handlers
 *
 * @PluginID("node_status")
 */
class Status extends FilterPluginBase {

  public function adminSummary() { }

  protected function operatorForm(&$form, &$form_state) { }

  public function canExpose() { return FALSE; }

  public function query() {
    $table = $this->ensureMyTable();
    $this->query->addWhereExpression($this->options['group'], "$table.status = 1 OR ($table.uid = ***CURRENT_USER*** AND ***CURRENT_USER*** <> 0 AND ***VIEW_OWN_UNPUBLISHED_NODES*** = 1) OR ***BYPASS_NODE_ACCESS*** = 1");
  }

}
