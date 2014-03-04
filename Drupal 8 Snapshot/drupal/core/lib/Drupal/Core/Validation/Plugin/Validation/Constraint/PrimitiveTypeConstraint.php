<?php

/**
 * @file
 * Contains \Drupal\Core\Validation\Plugin\Validation\Constraint\PrimitiveTypeConstraint.
 */

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Symfony\Component\Validator\Constraint;

/**
 * Supports validating all primitive types.
 *
 * @Plugin(
 *   id = "PrimitiveType",
 *   label = @Translation("Primitive type", context = "Validation")
 * )
 */
class PrimitiveTypeConstraint extends Constraint {

  public $message = 'This value should be of the correct primitive type.';
}
