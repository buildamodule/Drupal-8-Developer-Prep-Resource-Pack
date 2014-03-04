<?php

/**
 * @file
 * Definition of Drupal\views_test_data\Plugin\views\display\DisplayNoAreaTest.
 */

namespace Drupal\views_test_data\Plugin\views\display;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a Display test plugin with areas disabled.
 *
 * @Plugin(
 *   id = "display_no_area_test",
 *   title = @Translation("Display test no area"),
 *   theme = "views_view",
 *   contextual_links_locations = {"view"}
 * )
 */
class DisplayNoAreaTest extends DisplayTest {

  /**
   * Whether the display allows area plugins.
   *
   * @var bool
   *   TRUE if the display can use areas, or FALSE otherwise.
   */
  protected $usesAreas = FALSE;

}
