<?php

/**
 * @file
 * Contains \Drupal\menu_test\TestControllers.
 */

namespace Drupal\menu_test;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityInterface;

/**
 * Controllers for testing the menu integration routing system.
 */
class TestControllers {

  /**
   * Returns page to be used as a login path.
   */
  public function testLogin() {
    return 'This is TestControllers::testLogin.';
  }

  /**
   * Prints out test data.
   */
  public function test1() {
    return 'test1';
  }

  /**
   * Prints out test data.
   */
  public function test2() {
    return 'test2';
  }

  /**
   * Prints out test data.
   *
   * @param string|null $placeholder
   *   A placeholder for the return string.
   *
   * @return string
   *   The string for this route.
   */
  public function testDefaults($placeholder = NULL) {
    if ($placeholder) {
      return String::format("Sometimes there is a placeholder: '@placeholder'.", array('@placeholder' => $placeholder));
    }
    else {
      return String::format('Sometimes there is no placeholder.');
    }
  }

}
