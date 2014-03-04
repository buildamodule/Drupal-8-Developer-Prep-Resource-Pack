<?php

/**
 * @file
 * Definition of Drupal\Core\Database\Query\NoFieldsException
 */

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\DatabaseException;

use InvalidArgumentException;

/**
 * Exception thrown if an insert query doesn't specify insert or default fields.
 */
class NoFieldsException extends InvalidArgumentException implements DatabaseException {}
