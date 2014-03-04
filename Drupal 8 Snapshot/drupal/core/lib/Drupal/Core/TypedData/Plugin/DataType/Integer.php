<?php

/**
 * @file
 * Contains \Drupal\Core\TypedData\Plugin\DataType\Integer.
 */

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\TypedData\Annotation\DataType;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\TypedData\PrimitiveBase;
use Drupal\Core\TypedData\Type\IntegerInterface;

/**
 * The integer data type.
 *
 * The plain value of an integer is a regular PHP integer. For setting the value
 * any PHP variable that casts to an integer may be passed.
 *
 * @DataType(
 *   id = "integer",
 *   label = @Translation("Integer")
 * )
 */
class Integer extends PrimitiveBase implements IntegerInterface {

}
