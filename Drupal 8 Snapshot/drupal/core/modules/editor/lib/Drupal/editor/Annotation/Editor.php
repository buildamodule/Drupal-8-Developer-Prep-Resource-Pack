<?php

/**
 * @file
 * Contains \Drupal\editor\Annotation\Editor.
 */

namespace Drupal\editor\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Editor annotation object.
 *
 * @Annotation
 */
class Editor extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the editor plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * Whether the editor supports the inline editing provided by the Edit module.
   *
   * @var boolean
   */
  public $supports_inline_editing;

}
