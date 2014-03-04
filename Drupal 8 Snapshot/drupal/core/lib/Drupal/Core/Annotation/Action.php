<?php

/**
 * @file
 * Contains \Drupal\Core\Annotation\Action.
 */

namespace Drupal\Core\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Action annotation object.
 *
 * @see \Drupal\Core\Action\ActionInterface
 * @see \Drupal\Core\Action\ActionManager
 *
 * @Annotation
 */
class Action extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the action plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The path for a confirmation form for this action.
   *
   * @todo Change this to accept a route.
   * @todo Provide a more generic way to allow an action to be confirmed first.
   *
   * @var string (optional)
   */
  public $confirm_form_path = '';

  /**
   * The entity type the action can apply to.
   *
   * @todo Replace with \Drupal\Core\Plugin\Context\Context.
   *
   * @var string
   */
  public $type = '';

}
