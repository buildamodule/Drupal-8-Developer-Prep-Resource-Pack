<?php

/**
 * @file
 * Contains \Drupal\edit\Ajax\EntitySavedCommand.
 */

namespace Drupal\edit\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to indicate the entity was loaded from TempStore and saved into
 * the database.
 */
class EntitySavedCommand extends BaseCommand {

  /**
   * Constructs a EntitySaveCommand object.
   *
   * @param string $data
   *   The data to pass on to the client side.
   */
  public function __construct($data) {
    parent::__construct('editEntitySaved', $data);
  }

}
