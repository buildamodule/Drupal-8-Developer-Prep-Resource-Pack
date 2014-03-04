<?php

/**
 * @file
 * Contains \Drupal\Core\Validation\Constraint\BundleConstraint.
 */

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;


/**
 * Checks if a value is a valid entity type.
 *
 * @todo: Move this below the entity core component.
 *
 * @Plugin(
 *   id = "Bundle",
 *   label = @Translation("Bundle", context = "Validation"),
 *   type = { "entity", "entity_reference" }
 * )
 */
class BundleConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The entity must be of bundle %bundle.';

  /**
   * The bundle option.
   *
   * @var string|array
   */
  public $bundle;

  /**
   * Gets the bundle option as array.
   *
   * @return array
   */
  public function getBundleOption() {
    // Support passing the bundle as string, but force it to be an array.
    if (!is_array($this->bundle)) {
      $this->bundle = array($this->bundle);
    }
    return $this->bundle;
  }

  /**
   * Overrides Constraint::getDefaultOption().
   */
  public function getDefaultOption() {
    return 'bundle';
  }

  /**
   * Overrides Constraint::getRequiredOptions().
   */
  public function getRequiredOptions() {
    return array('bundle');
  }
}
