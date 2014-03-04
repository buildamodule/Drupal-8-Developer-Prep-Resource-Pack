<?php

/**
 * @file
 * Contains Drupal\Core\PathProcessor\PathProcessorFront.
 */

namespace Drupal\Core\PathProcessor;

use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes the inbound path by resolving it to the front page if empty.
 */
class PathProcessorFront implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * A config factory for retrieving required config settings.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * Constructs a PathProcessorFront object.
   *
   * @param Drupal\Core\Config\ConfigFactory $config
   *   A config factory for retrieving the site front page configuration.
   */
  public function __construct(ConfigFactory $config) {
    $this->config = $config;
  }

  /**
   * Implements Drupal\Core\PathProcessor\InboundPathProcessorInterface::processInbound().
   */
  public function processInbound($path, Request $request) {
    if (empty($path)) {
      $path = $this->config->get('system.site')->get('page.front');
      if (empty($path)) {
        $path = 'user';
      }
    }
    return $path;
  }

  /**
   * Implements Drupal\Core\PathProcessor\OutboundPathProcessorInterface::processOutbound().
   */
  public function processOutbound($path, &$options = array(), Request $request = NULL) {
    // The special path '<front>' links to the default front page.
    if ($path == '<front>') {
      $path = '';
    }
    return $path;
  }

}
