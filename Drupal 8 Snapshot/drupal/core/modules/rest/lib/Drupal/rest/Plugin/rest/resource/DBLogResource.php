<?php

/**
 * @file
 * Definition of Drupal\rest\Plugin\rest\resource\DBLogResource.
 */

namespace Drupal\rest\Plugin\rest\resource;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource for database watchdog log entries.
 *
 * @Plugin(
 *   id = "dblog",
 *   label = @Translation("Watchdog database log")
 * )
 */
class DBLogResource extends ResourceBase {

  /**
   * Overrides \Drupal\rest\Plugin\ResourceBase::routes().
   */
  public function routes() {
    // Only expose routes if the dblog module is enabled.
    if (module_exists('dblog')) {
      return parent::routes();
    }
    return new RouteCollection();
  }

  /**
   * Responds to GET requests.
   *
   * Returns a watchdog log entry for the specified ID.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the log entry.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get($id = NULL) {
    if ($id) {
      $record = db_query("SELECT * FROM {watchdog} WHERE wid = :wid", array(':wid' => $id))
        ->fetchAssoc();
      if (!empty($record)) {
        return new ResourceResponse($record);
      }
    }
    throw new NotFoundHttpException(t('Log entry with ID @id was not found', array('@id' => $id)));
  }
}
