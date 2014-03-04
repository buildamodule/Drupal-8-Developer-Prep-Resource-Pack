<?php

/**
 * @file
 * Contains \Drupal\image\Plugin\Core\Entity\ImageStyle.
 */

namespace Drupal\image\Plugin\Core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\image\ImageEffectBag;
use Drupal\image\ImageEffectInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Url;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Defines an image style configuration entity.
 *
 * @EntityType(
 *   id = "image_style",
 *   label = @Translation("Image style"),
 *   module = "image",
 *   controllers = {
 *     "form" = {
 *       "add" = "Drupal\image\Form\ImageStyleAddForm",
 *       "edit" = "Drupal\image\Form\ImageStyleEditForm",
 *       "delete" = "Drupal\image\Form\ImageStyleDeleteForm"
 *     },
 *     "storage" = "Drupal\Core\Config\Entity\ConfigStorageController",
 *     "list" = "Drupal\image\ImageStyleListController",
 *     "access" = "Drupal\image\ImageStyleAccessController"
 *   },
 *   uri_callback = "image_style_entity_uri",
 *   config_prefix = "image.style",
 *   entity_keys = {
 *     "id" = "name",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class ImageStyle extends ConfigEntityBase implements ImageStyleInterface {

  /**
   * The name of the image style to use as replacement upon delete.
   *
   * @var string
   */
  protected $replacementID;

  /**
   * The name of the image style.
   *
   * @var string
   */
  public $name;

  /**
   * The image style label.
   *
   * @var string
   */
  public $label;

  /**
   * The UUID for this entity.
   *
   * @var string
   */
  public $uuid;

  /**
   * The array of image effects for this image style.
   *
   * @var array
   */
  protected $effects = array();

  /**
   * Holds the collection of image effects that are used by this image style.
   *
   * @var \Drupal\image\ImageEffectBag
   */
  protected $effectsBag;

  /**
   * Overrides Drupal\Core\Entity\Entity::id().
   */
  public function id() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageControllerInterface $storage_controller, $update = TRUE) {
    if ($update) {
      if (!empty($this->original) && $this->id() !== $this->original->id()) {
        // The old image style name needs flushing after a rename.
        $this->original->flush();
        // Update field instance settings if necessary.
        static::replaceImageStyle($this);
      }
      else {
        // Flush image style when updating without changing the name.
        $this->flush();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageControllerInterface $storage_controller, array $entities) {
    foreach ($entities as $style) {
      // Flush cached media for the deleted style.
      $style->flush();
      // Check whether field instance settings need to be updated.
      // In case no replacement style was specified, all image fields that are
      // using the deleted style are left in a broken state.
      if ($new_id = $style->get('replacementID')) {
        // The deleted ID is still set as originalID.
        $style->set('name', $new_id);
        static::replaceImageStyle($style);
      }
    }
  }

  /**
   * Update field instance settings if the image style name is changed.
   *
   * @param \Drupal\image\ImageStyleInterface $style
   *   The image style.
   */
  protected static function replaceImageStyle(ImageStyleInterface $style) {
    if ($style->id() != $style->getOriginalID()) {
      $instances = field_read_instances();
      // Loop through all fields searching for image fields.
      foreach ($instances as $instance) {
        if ($instance->getField()->type == 'image') {
          $view_modes = entity_get_view_modes($instance['entity_type']);
          $view_modes = array('default') + array_keys($view_modes);
          foreach ($view_modes as $view_mode) {
            $display = entity_get_display($instance['entity_type'], $instance['bundle'], $view_mode);
            $display_options = $display->getComponent($instance['field_name']);

            // Check if the formatter involves an image style.
            if ($display_options && $display_options['type'] == 'image' && $display_options['settings']['image_style'] == $style->getOriginalID()) {
              // Update display information for any instance using the image
              // style that was just deleted.
              $display_options['settings']['image_style'] = $style->id();
              $display->setComponent($instance['field_name'], $display_options)
                ->save();
            }
          }
          $entity_form_display = entity_get_form_display($instance['entity_type'], $instance['bundle'], 'default');
          $widget_configuration = $entity_form_display->getComponent($instance['field_name']);
          if ($widget_configuration['settings']['preview_image_style'] == $style->getOriginalID()) {
            $widget_options['settings']['preview_image_style'] = $style->id();
            $entity_form_display->setComponent($instance['field_name'], $widget_options)
              ->save();
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildUri($uri) {
    $scheme = file_uri_scheme($uri);
    if ($scheme) {
      $path = file_uri_target($uri);
    }
    else {
      $path = $uri;
      $scheme = file_default_scheme();
    }
    return $scheme . '://styles/' . $this->id() . '/' . $scheme . '/' . $path;
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrl($path, $clean_urls = NULL) {
    $uri = $this->buildUri($path);
    // The token query is added even if the
    // 'image.settings:allow_insecure_derivatives' configuration is TRUE, so
    // that the emitted links remain valid if it is changed back to the default
    // FALSE. However, sites which need to prevent the token query from being
    // emitted at all can additionally set the
    // 'image.settings:suppress_itok_output' configuration to TRUE to achieve
    // that (if both are set, the security token will neither be emitted in the
    // image derivative URL nor checked for in
    // \Drupal\image\ImageStyleInterface::deliver()).
    $token_query = array();
    if (!\Drupal::config('image.settings')->get('suppress_itok_output')) {
      $token_query = array(IMAGE_DERIVATIVE_TOKEN => $this->getPathToken(file_stream_wrapper_uri_normalize($path)));
    }

    if ($clean_urls === NULL) {
      // Assume clean URLs unless the request tells us otherwise.
      $clean_urls = TRUE;
      try {
        $request = \Drupal::request();
        $clean_urls = $request->attributes->get('clean_urls');
      }
      catch (ServiceNotFoundException $e) {
      }
    }

    // If not using clean URLs, the image derivative callback is only available
    // with the script path. If the file does not exist, use url() to ensure
    // that it is included. Once the file exists it's fine to fall back to the
    // actual file path, this avoids bootstrapping PHP once the files are built.
    if ($clean_urls === FALSE && file_uri_scheme($uri) == 'public' && !file_exists($uri)) {
      $directory_path = file_stream_wrapper_get_instance_by_uri($uri)->getDirectoryPath();
      return url($directory_path . '/' . file_uri_target($uri), array('absolute' => TRUE, 'query' => $token_query));
    }

    $file_url = file_create_url($uri);
    // Append the query string with the token, if necessary.
    if ($token_query) {
      $file_url .= (strpos($file_url, '?') !== FALSE ? '&' : '?') . Url::buildQuery($token_query);
    }

    return $file_url;
  }

  /**
   * {@inheritdoc}
   */
  public function flush($path = NULL) {
    // A specific image path has been provided. Flush only that derivative.
    if (isset($path)) {
      $derivative_uri = $this->buildUri($path);
      if (file_exists($derivative_uri)) {
        file_unmanaged_delete($derivative_uri);
      }
      return $this;
    }

    // Delete the style directory in each registered wrapper.
    $wrappers = file_get_stream_wrappers(STREAM_WRAPPERS_WRITE_VISIBLE);
    foreach ($wrappers as $wrapper => $wrapper_data) {
      file_unmanaged_delete_recursive($wrapper . '://styles/' . $this->id());
    }

    // Let other modules update as necessary on flush.
    $module_handler = \Drupal::moduleHandler();
    $module_handler->invokeAll('image_style_flush', array($this));

    // Clear field caches so that formatters may be added for this style.
    field_info_cache_clear();
    drupal_theme_rebuild();

    // Clear page caches when flushing.
    if ($module_handler->moduleExists('block')) {
      \Drupal::cache('block')->deleteAll();
    }
    \Drupal::cache('page')->deleteAll();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function createDerivative($original_uri, $derivative_uri) {
    // Get the folder for the final location of this style.
    $directory = drupal_dirname($derivative_uri);

    // Build the destination folder tree if it doesn't already exist.
    if (!file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      watchdog('image', 'Failed to create style directory: %directory', array('%directory' => $directory), WATCHDOG_ERROR);
      return FALSE;
    }

    $image = \Drupal::service('image.factory')->get($original_uri);
    if (!$image->getResource()) {
      return FALSE;
    }

    foreach ($this->getEffects() as $effect) {
      $effect->applyEffect($image);
    }

    if (!$image->save($derivative_uri)) {
      if (file_exists($derivative_uri)) {
        watchdog('image', 'Cached image file %destination already exists. There may be an issue with your rewrite configuration.', array('%destination' => $derivative_uri), WATCHDOG_ERROR);
      }
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions) {
    foreach ($this->getEffects() as $effect) {
      $effect->transformDimensions($dimensions);
    }
  }

  /**
   * Generates a token to protect an image style derivative.
   *
   * This prevents unauthorized generation of an image style derivative,
   * which can be costly both in CPU time and disk space.
   *
   * @param string $uri
   *   The URI of the original image of this style.
   *
   * @return string
   *   An eight-character token which can be used to protect image style
   *   derivatives against denial-of-service attacks.
   */
  public function getPathToken($uri) {
    // Return the first eight characters.
    return substr(Crypt::hmacBase64($this->id() . ':' . $uri, drupal_get_private_key() . drupal_get_hash_salt()), 0, 8);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteImageEffect(ImageEffectInterface $effect) {
    $this->getEffects()->removeInstanceID($effect->getUuid());
    $this->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEffect($effect) {
    return $this->getEffects()->get($effect);
  }

  /**
   * {@inheritdoc}
   */
  public function getEffects() {
    if (!$this->effectsBag) {
      $this->effectsBag = new ImageEffectBag(\Drupal::service('plugin.manager.image.effect'), $this->effects);
    }
    return $this->effectsBag;
  }

  /**
   * {@inheritdoc}
   */
  public function saveImageEffect(array $configuration) {
    $effect_id = $this->getEffects()->updateConfiguration($configuration);
    $this->save();
    return $effect_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportProperties() {
    $properties = parent::getExportProperties();
    $properties['effects'] = $this->getEffects()->sort()->getConfiguration();
    return $properties;
  }

}
