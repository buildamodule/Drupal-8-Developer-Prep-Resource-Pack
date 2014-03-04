<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\exposed_form\Basic.
 */

namespace Drupal\views\Plugin\views\exposed_form;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Exposed form plugin that provides a basic exposed form.
 *
 * @ingroup views_exposed_form_plugins
 *
 * @Plugin(
 *   id = "basic",
 *   title = @Translation("Basic"),
 *   help = @Translation("Basic exposed form")
 * )
 */
class Basic extends ExposedFormPluginBase {

}
