<?php

/**
 * @file
 * Contains \Drupal\node\Plugin\Action\PromoteNode.
 */

namespace Drupal\node\Plugin\Action;

use Drupal\Core\Annotation\Action;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Action\ActionBase;

/**
 * Promotes a node.
 *
 * @Action(
 *   id = "node_promote_action",
 *   label = @Translation("Promote selected content to front page"),
 *   type = "node"
 * )
 */
class PromoteNode extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $entity->status = NODE_PUBLISHED;
    $entity->promote = NODE_PROMOTED;
    $entity->save();
  }

}
