<?php

/**
 * @file
 * Definition of Drupal\node\Plugin\views\field\RevisionLinkDelete.
 */

namespace Drupal\node\Plugin\views\field;

use Drupal\node\Plugin\views\field\RevisionLink;
use Drupal\Component\Annotation\PluginID;
use Drupal\views\ResultRow;

/**
 * Field handler to present link to delete a node revision.
 *
 * @ingroup views_field_handlers
 *
 * @PluginID("node_revision_link_delete")
 */
class RevisionLinkDelete extends RevisionLink {

  public function access() {
    return user_access('delete revisions') || user_access('administer nodes');
  }

  protected function renderLink($data, ResultRow $values) {
    list($node, $vid) = $this->get_revision_entity($values, 'delete');
    if (!isset($vid)) {
      return;
    }

    // Current revision cannot be deleted.
    if ($node->isDefaultRevision()) {
      return;
    }

    $this->options['alter']['make_link'] = TRUE;
    $this->options['alter']['path'] = 'node/' . $node->id() . "/revisions/$vid/delete";
    $this->options['alter']['query'] = drupal_get_destination();

    return !empty($this->options['text']) ? $this->options['text'] : t('Delete');
  }

}
