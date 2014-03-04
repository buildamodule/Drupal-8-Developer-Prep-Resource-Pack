<?php

/**
 * @file
 * Contains \Drupal\image\Plugin\ImageEffect\DesaturateImageEffect.
 */

namespace Drupal\image\Plugin\ImageEffect;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\Annotation\ImageEffect;
use Drupal\image\ImageEffectBase;

/**
 * Desaturates (grayscale) an image resource.
 *
 * @ImageEffect(
 *   id = "image_desaturate",
 *   label = @Translation("Desaturate"),
 *   description = @Translation("Desaturate converts an image to grayscale.")
 * )
 */
class DesaturateImageEffect extends ImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions) {
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    if (!$image->desaturate()) {
      watchdog('image', 'Image desaturate failed using the %toolkit toolkit on %path (%mimetype, %dimensions)', array('%toolkit' => $image->getToolkitId(), '%path' => $image->getSource(), '%mimetype' => $image->getMimeType(), '%dimensions' => $image->getWidth() . 'x' . $image->getHeight()), WATCHDOG_ERROR);
      return FALSE;
    }
    return TRUE;
  }

}
