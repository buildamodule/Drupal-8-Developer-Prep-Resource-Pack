<?php

/**
 * @file
 * Definition of Drupal\views_test_data\Plugin\views\access\StaticTest.
 */

namespace Drupal\views_test_data\Plugin\views\access;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Tests a static access plugin.
 *
 * @Plugin(
 *   id = "test_static",
 *   title = @Translation("Static test access plugin"),
 *   help = @Translation("Provides a static test access plugin.")
 * )
 */
class StaticTest extends AccessPluginBase {

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['access'] = array('default' => FALSE, 'bool' => TRUE);

    return $options;
  }

  public function access(AccountInterface $account) {
    return !empty($this->options['access']);
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    if (!empty($this->options['access'])) {
      $route->setRequirement('_access', 'TRUE');
    }
  }

}
