<?php

/**
 * @file
 * Contains \Drupal\image_module_test\Plugin\ImageEffect\NullTestImageEffect.
 */

namespace Drupal\image_module_test\Plugin\ImageEffect;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\Annotation\ImageEffect;
use Drupal\image\ImageEffectBase;

/**
 * Performs no operation on an image resource.
 *
 * @ImageEffect(
 *   id = "image_module_test_null",
 *   label = @Translation("Image module test")
 * )
 */
class NullTestImageEffect extends ImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return TRUE;
  }

}
