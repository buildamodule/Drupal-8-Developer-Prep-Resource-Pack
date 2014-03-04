<?php

/**
 * @file
 * Contains \Drupal\language_test\Controller\LanguageTestController.
 */

namespace Drupal\language_test\Controller;

use Drupal\Core\Controller\ControllerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Controller routines for language_test routes.
 */
class LanguageTestController implements ControllerInterface {

  /**
   * The HTTP kernel service.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs a new LanguageTestController object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
   *   An HTTP kernel.
   */
  public function __construct(HttpKernelInterface $httpKernel) {
    $this->httpKernel = $httpKernel;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('http_kernel'));
  }

  /**
   * Returns links to the current page with different langcodes.
   *
   * Using #type 'link' causes these links to be rendered with l().
   */
  public function typeLinkActiveClass() {
    // We assume that 'en' and 'fr' have been configured.
    $languages = language_list();
    return array(
      'no_language' => array(
        '#type' => 'link',
        '#title' => t('Link to the current path with no langcode provided.'),
        '#href' => current_path(),
        '#options' => array(
          'attributes' => array(
            'id' => 'no_lang_link',
          ),
        ),
      ),
      'fr' => array(
        '#type' => 'link',
        '#title' => t('Link to a French version of the current path.'),
        '#href' => current_path(),
        '#options' => array(
          'language' => $languages['fr'],
          'attributes' => array(
            'id' => 'fr_link',
          ),
        ),
      ),
      'en' => array(
        '#type' => 'link',
        '#title' => t('Link to an English version of the current path.'),
        '#href' => current_path(),
        '#options' => array(
          'language' => $languages['en'],
          'attributes' => array(
            'id' => 'en_link',
          ),
        ),
      ),
    );
  }

  /**
   * Uses a sub request to retrieve the 'user' page.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The kernels response to the sub request.
   */
  public function testSubRequest() {
    $request = Request::createFromGlobals();
    $server = $request->server->all();
    if (basename($server['SCRIPT_FILENAME']) != basename($server['SCRIPT_NAME'])) {
      // We need this for when the test is executed by run-tests.sh.
      // @todo Remove this once run-tests.sh has been converted to use a Request
      //   object.
      $server['SCRIPT_FILENAME'] = $server['SCRIPT_NAME'];
      $base_path = ltrim($server['REQUEST_URI'], '/');
    }
    else {
      $base_path = $request->getBasePath();
    }
    $sub_request = Request::create($base_path . '/user', 'GET', $request->query->all(), $request->cookies->all(), array(), $server);
    return $this->httpKernel->handle($sub_request, HttpKernelInterface::SUB_REQUEST);
  }

}
