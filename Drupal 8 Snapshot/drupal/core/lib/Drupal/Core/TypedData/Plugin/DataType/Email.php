<?php

/**
 * @file
 * Contains \Drupal\Core\TypedData\Plugin\DataType\Email.
 */

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\TypedData\Annotation\DataType;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\TypedData\Type\StringInterface;

/**
 * The Email data type.
 *
 * The plain value of Email is the email address represented as PHP string.
 *
 * @DataType(
 *   id = "email",
 *   label = @Translation("Email"),
 *   constraints = {"Email" = TRUE}
 * )
 */
class Email extends String implements StringInterface {

}
