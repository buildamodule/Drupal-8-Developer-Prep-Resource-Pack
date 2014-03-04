<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Image\ToolkitGdTest.
 */

namespace Drupal\system\Tests\Image;

use Drupal\Core\Image\ImageInterface;
use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Test the core GD image manipulation functions.
 */
class ToolkitGdTest extends DrupalUnitTestBase {
  // Colors that are used in testing.
  protected $black       = array(0, 0, 0, 0);
  protected $red         = array(255, 0, 0, 0);
  protected $green       = array(0, 255, 0, 0);
  protected $blue        = array(0, 0, 255, 0);
  protected $yellow      = array(255, 255, 0, 0);
  protected $fuchsia     = array(255, 0, 255, 0); // Used as background colors.
  protected $transparent = array(0, 0, 0, 127);
  protected $white       = array(255, 255, 255, 0);

  protected $width = 40;
  protected $height = 20;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('system', 'simpletest');

  public static function getInfo() {
    return array(
      'name' => 'Image GD manipulation tests',
      'description' => 'Check that core image manipulations work properly: scale, resize, rotate, crop, scale and crop, and desaturate.',
      'group' => 'Image',
    );
  }

  protected function checkRequirements() {
    $gd_available = FALSE;
    if ($check = get_extension_funcs('gd')) {
      if (in_array('imagegd2', $check)) {
        // GD2 support is available.
        $gd_available =  TRUE;
      }
    }
    if (!$gd_available) {
      return array(
        'Image manipulations for the GD toolkit cannot run because the GD toolkit is not available.',
      );
    }
    return parent::checkRequirements();
  }

  /**
   * Function to compare two colors by RGBa.
   */
  function colorsAreEqual($color_a, $color_b) {
    // Fully transparent pixels are equal, regardless of RGB.
    if ($color_a[3] == 127 && $color_b[3] == 127) {
      return TRUE;
    }

    foreach ($color_a as $key => $value) {
      if ($color_b[$key] != $value) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Function for finding a pixel's RGBa values.
   */
  function getPixelColor(ImageInterface $image, $x, $y) {
    $color_index = imagecolorat($image->getResource(), $x, $y);

    $transparent_index = imagecolortransparent($image->getResource());
    if ($color_index == $transparent_index) {
      return array(0, 0, 0, 127);
    }

    return array_values(imagecolorsforindex($image->getResource(), $color_index));
  }

  /**
   * Since PHP can't visually check that our images have been manipulated
   * properly, build a list of expected color values for each of the corners and
   * the expected height and widths for the final images.
   */
  function testManipulations() {
    // Typically the corner colors will be unchanged. These colors are in the
    // order of top-left, top-right, bottom-right, bottom-left.
    $default_corners = array($this->red, $this->green, $this->blue, $this->transparent);

    // A list of files that will be tested.
    $files = array(
      'image-test.png',
      'image-test.gif',
      'image-test.jpg',
    );

    // Setup a list of tests to perform on each type.
    $operations = array(
      'resize' => array(
        'function' => 'resize',
        'arguments' => array(20, 10),
        'width' => 20,
        'height' => 10,
        'corners' => $default_corners,
      ),
      'scale_x' => array(
        'function' => 'scale',
        'arguments' => array(20, NULL),
        'width' => 20,
        'height' => 10,
        'corners' => $default_corners,
      ),
      'scale_y' => array(
        'function' => 'scale',
        'arguments' => array(NULL, 10),
        'width' => 20,
        'height' => 10,
        'corners' => $default_corners,
      ),
      'upscale_x' => array(
        'function' => 'scale',
        'arguments' => array(80, NULL, TRUE),
        'width' => 80,
        'height' => 40,
        'corners' => $default_corners,
      ),
      'upscale_y' => array(
        'function' => 'scale',
        'arguments' => array(NULL, 40, TRUE),
        'width' => 80,
        'height' => 40,
        'corners' => $default_corners,
      ),
      'crop' => array(
        'function' => 'crop',
        'arguments' => array(12, 4, 16, 12),
        'width' => 16,
        'height' => 12,
        'corners' => array_fill(0, 4, $this->white),
      ),
      'scale_and_crop' => array(
        'function' => 'scaleAndCrop',
        'arguments' => array(10, 8),
        'width' => 10,
        'height' => 8,
        'corners' => array_fill(0, 4, $this->black),
      ),
    );

    // Systems using non-bundled GD2 don't have imagerotate. Test if available.
    if (function_exists('imagerotate')) {
      $operations += array(
        'rotate_5' => array(
          'function' => 'rotate',
          'arguments' => array(5, 0xFF00FF), // Fuchsia background.
          'width' => 42,
          'height' => 24,
          'corners' => array_fill(0, 4, $this->fuchsia),
        ),
        'rotate_90' => array(
          'function' => 'rotate',
          'arguments' => array(90, 0xFF00FF), // Fuchsia background.
          'width' => 20,
          'height' => 40,
          'corners' => array($this->transparent, $this->red, $this->green, $this->blue),
        ),
        'rotate_transparent_5' => array(
          'function' => 'rotate',
          'arguments' => array(5),
          'width' => 42,
          'height' => 24,
          'corners' => array_fill(0, 4, $this->transparent),
        ),
        'rotate_transparent_90' => array(
          'function' => 'rotate',
          'arguments' => array(90),
          'width' => 20,
          'height' => 40,
          'corners' => array($this->transparent, $this->red, $this->green, $this->blue),
        ),
      );
    }

    // Systems using non-bundled GD2 don't have imagefilter. Test if available.
    if (function_exists('imagefilter')) {
      $operations += array(
        'desaturate' => array(
          'function' => 'desaturate',
          'arguments' => array(),
          'height' => 20,
          'width' => 40,
          // Grayscale corners are a bit funky. Each of the corners are a shade of
          // gray. The values of these were determined simply by looking at the
          // final image to see what desaturated colors end up being.
          'corners' => array(
            array_fill(0, 3, 76) + array(3 => 0),
            array_fill(0, 3, 149) + array(3 => 0),
            array_fill(0, 3, 29) + array(3 => 0),
            array_fill(0, 3, 225) + array(3 => 127)
          ),
        ),
      );
    }

    $toolkit = $this->container->get('image.toolkit.manager')->createInstance('gd');
    $image_factory = $this->container->get('image.factory')->setToolkit($toolkit);
    foreach ($files as $file) {
      foreach ($operations as $op => $values) {
        // Load up a fresh image.
        $image = $image_factory->get(drupal_get_path('module', 'simpletest') . '/files/' . $file);
        if (!$image) {
          $this->fail(t('Could not load image %file.', array('%file' => $file)));
          continue 2;
        }

        // All images should be converted to truecolor when loaded.
        $image_truecolor = imageistruecolor($image->getResource());
        $this->assertTrue($image_truecolor, format_string('Image %file after load is a truecolor image.', array('%file' => $file)));

        if ($image->getExtension() == 'gif') {
          if ($op == 'desaturate') {
            // Transparent GIFs and the imagefilter function don't work together.
            $values['corners'][3][3] = 0;
          }
        }

        // Perform our operation.
        call_user_func_array(array($image, $values['function']), $values['arguments']);

        // To keep from flooding the test with assert values, make a general
        // value for whether each group of values fail.
        $correct_dimensions_real = TRUE;
        $correct_dimensions_object = TRUE;
        $correct_colors = TRUE;

        // Check the real dimensions of the image first.
        if (imagesy($image->getResource()) != $values['height'] || imagesx($image->getResource()) != $values['width']) {
          $correct_dimensions_real = FALSE;
        }

        // Check that the image object has an accurate record of the dimensions.
        if ($image->getWidth() != $values['width'] || $image->getHeight() != $values['height']) {
          $correct_dimensions_object = FALSE;
        }

        $directory = $this->public_files_directory .'/imagetest';
        file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
        $image->save($directory . '/' . $op . '.' . $image->getExtension());

        $this->assertTrue($correct_dimensions_real, format_string('Image %file after %action action has proper dimensions.', array('%file' => $file, '%action' => $op)));
        $this->assertTrue($correct_dimensions_object, format_string('Image %file object after %action action is reporting the proper height and width values.', array('%file' => $file, '%action' => $op)));

        // JPEG colors will always be messed up due to compression.
        if ($image->getExtension() != 'jpg') {
          // Now check each of the corners to ensure color correctness.
          foreach ($values['corners'] as $key => $corner) {
            // Get the location of the corner.
            switch ($key) {
              case 0:
                $x = 0;
                $y = 0;
                break;
              case 1:
                $x = $values['width'] - 1;
                $y = 0;
                break;
              case 2:
                $x = $values['width'] - 1;
                $y = $values['height'] - 1;
                break;
              case 3:
                $x = 0;
                $y = $values['height'] - 1;
                break;
            }
            $color = $this->getPixelColor($image, $x, $y);
            $correct_colors = $this->colorsAreEqual($color, $corner);
            $this->assertTrue($correct_colors, format_string('Image %file object after %action action has the correct color placement at corner %corner.', array('%file' => $file, '%action' => $op, '%corner' => $key)));
          }
        }
      }
    }

  }
}
