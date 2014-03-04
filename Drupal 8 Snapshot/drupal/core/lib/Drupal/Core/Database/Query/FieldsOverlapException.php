<?php

/**
 * @file
 * Definition of Drupal\Core\Database\Query\FieldsOverlapExceptoin
 */

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\DatabaseException;

use InvalidArgumentException;

/**
 * Exception thrown if an insert query specifies a field twice.
 *
 * It is not allowed to specify a field as default and insert field, this
 * exception is thrown if that is the case.
 */
class FieldsOverlapException extends InvalidArgumentException implements DatabaseException {}
