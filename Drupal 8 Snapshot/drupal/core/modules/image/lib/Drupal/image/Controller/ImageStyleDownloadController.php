<?php

/**
 * @file
 * Contains \Drupal\image\Controller\ImageStyleDownloadController.
 */

namespace Drupal\image\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\system\FileDownloadController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Defines a controller to serve image styles.
 */
class ImageStyleDownloadController extends FileDownloadController implements ControllerInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The translator service.
   *
   * @var \Drupal\Core\StringTranslation\Translator\TranslatorInterface
   */
  protected $translator;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs a ImageStyleDownloadController object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\StringTranslation\Translator\TranslatorInterface $translator
   *   The translator service.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ConfigFactory $config_factory, LockBackendInterface $lock, TranslatorInterface $translator, ImageFactory $image_factory) {
    parent::__construct($module_handler);
    $this->configFactory = $config_factory;
    $this->lock = $lock;
    $this->translator = $translator;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('lock'),
      $container->get('string_translation'),
      $container->get('image.factory')
    );
  }

  /**
   * Generates a derivative, given a style and image path.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to deliver.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   */
  public function deliver(Request $request, $scheme, ImageStyleInterface $image_style) {
    $target = $request->query->get('file');
    $image_uri = $scheme . '://' . $target;

    // Check that the style is defined, the scheme is valid, and the image
    // derivative token is valid. Sites which require image derivatives to be
    // generated without a token can set the
    // 'image.settings:allow_insecure_derivatives' configuration to TRUE to
    // bypass the latter check, but this will increase the site's vulnerability
    // to denial-of-service attacks.
    $valid = !empty($image_style) && file_stream_wrapper_valid_scheme($scheme);
    if (!$this->configFactory->get('image.settings')->get('allow_insecure_derivatives')) {
      $valid &= $request->query->get(IMAGE_DERIVATIVE_TOKEN) === $image_style->getPathToken($image_uri);
    }
    if (!$valid) {
      throw new AccessDeniedHttpException();
    }

    $derivative_uri = $image_style->buildUri($image_uri);
    $headers = array();

    // If using the private scheme, let other modules provide headers and
    // control access to the file.
    if ($scheme == 'private') {
      if (file_exists($derivative_uri)) {
        return parent::download($request, $scheme);
      }
      else {
        $headers = $this->moduleHandler->invokeAll('file_download', array($image_uri));
        if (in_array(-1, $headers) || empty($headers)) {
          throw new AccessDeniedHttpException();
        }
      }
    }

    // Don't try to generate file if source is missing.
    if (!file_exists($image_uri)) {
      watchdog('image', 'Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.',  array('%source_image_path' => $image_uri, '%derivative_path' => $derivative_uri));
      return new Response($this->translator->translate('Error generating image, missing source file.'), 404);
    }

    // Don't start generating the image if the derivative already exists or if
    // generation is in progress in another thread.
    $lock_name = 'image_style_deliver:' . $image_style->id() . ':' . Crypt::hashBase64($image_uri);
    if (!file_exists($derivative_uri)) {
      $lock_acquired = $this->lock->acquire($lock_name);
      if (!$lock_acquired) {
        // Tell client to retry again in 3 seconds. Currently no browsers are
        // known to support Retry-After.
        throw new ServiceUnavailableHttpException(3, $this->translator->translate('Image generation in progress. Try again shortly.'));
      }
    }

    // Try to generate the image, unless another thread just did it while we
    // were acquiring the lock.
    $success = file_exists($derivative_uri) || $image_style->createDerivative($image_uri, $derivative_uri);

    if (!empty($lock_acquired)) {
      $this->lock->release($lock_name);
    }

    if ($success) {
      $image = $this->imageFactory->get($derivative_uri);
      $uri = $image->getSource();
      $headers += array(
        'Content-Type' => $image->getMimeType(),
        'Content-Length' => $image->getFileSize(),
      );
      return new BinaryFileResponse($uri, 200, $headers);
    }
    else {
      watchdog('image', 'Unable to generate the derived image located at %path.', array('%path' => $derivative_uri));
      return new Response($this->translator->translate('Error generating image.'), 500);
    }
  }

}
