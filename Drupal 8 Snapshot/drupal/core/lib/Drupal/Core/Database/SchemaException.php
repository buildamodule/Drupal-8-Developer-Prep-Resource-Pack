<?php

/**
 * @file
 * Definition of Drupal\Core\Database\SchemaException
 */

namespace Drupal\Core\Database;

use RuntimeException;

/**
 * Base exception for Schema-related errors.
 */
class SchemaException extends RuntimeException implements DatabaseException { }
