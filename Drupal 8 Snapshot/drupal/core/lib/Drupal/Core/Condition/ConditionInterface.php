<?php

/**
 * @file
 * Contains \Drupal\Core\Condition\ConditionInterface.
 */

namespace Drupal\Core\Condition;

use Drupal\Core\Executable\ExecutableInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Form\FormInterface;

/**
 * An interface for condition plugins.
 *
 * Condition plugins are context-aware and configurable. They support the
 * following keys in their plugin definitions:
 * - context: An array of context definitions, keyed by context name. Each
 *   context definition is a typed data definition describing the context. Check
 *   the typed data definition docs for details.
 * - configuration: An array of configuration option definitions, keyed by
 *   option name. Each option definition is a typed data definition describing
 *   the configuration option. Check the typed data definition docs for details.
 *
 * @todo Replace the dependency on \Drupal\Core\Form\FormInterface with a new
 *   interface from https://drupal.org/node/2006248.
 *
 * @see \Drupal\Core\TypedData\TypedDataManager::create()
 * @see \Drupal\Core\Executable\ExecutableInterface
 */
interface ConditionInterface extends ExecutableInterface, FormInterface {

  /**
   * Determines whether condition result will be negated.
   *
   * @return boolean
   *   Whether the condition result will be negated.
   */
  public function isNegated();

  /**
   * Evaluates the condition and returns TRUE or FALSE accordingly.
   *
   * @return bool
   *   TRUE if the condition has been met, FALSE otherwise.
   */
  public function evaluate();

  /**
   * Provides a human readable summary of the condition's configuration.
   */
  public function summary();

  /**
   * Sets the executable manager class.
   *
   * @param \Drupal\Core\Executable\ExecutableManagerInterface $executableManager
   *   The executable manager.
   */
  public function setExecutableManager(ExecutableManagerInterface $executableManager);

}
