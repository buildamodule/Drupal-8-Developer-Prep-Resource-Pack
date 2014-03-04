<?php

/**
 * Contains \Drupal\Core\Asset\JsCollectionRenderer.
 */

namespace Drupal\Core\Asset;

use Drupal\Core\Asset\AssetCollectionRendererInterface;

/**
 * Renders JavaScript assets.
 */
class JsCollectionRenderer implements AssetCollectionRendererInterface {

  /**
   * {@inheritdoc}
   */
  public function render(array $js_assets) {
    $elements = array();

    // A dummy query-string is added to filenames, to gain control over
    // browser-caching. The string changes on every update or full cache
    // flush, forcing browsers to load a new copy of the files, as the
    // URL changed. Files that should not be cached (see drupal_add_js())
    // get REQUEST_TIME as query-string instead, to enforce reload on every
    // page request.
    $default_query_string = variable_get('css_js_query_string', '0');

    // For inline JavaScript to validate as XHTML, all JavaScript containing
    // XHTML needs to be wrapped in CDATA. To make that backwards compatible
    // with HTML 4, we need to comment out the CDATA-tag.
    $embed_prefix = "\n<!--//--><![CDATA[//><!--\n";
    $embed_suffix = "\n//--><!]]>\n";

    // Since JavaScript may look for arguments in the URL and act on them, some
    // third-party code might require the use of a different query string.
    $js_version_string = variable_get('drupal_js_version_query_string', 'v=');

    // Defaults for each SCRIPT element.
    $element_defaults = array(
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => '',
    );

    // Loop through all JS assets.
    foreach ($js_assets as $js_asset) {
      // Element properties that do not depend on JS asset type.
      $element = $element_defaults;
      $element['#browsers'] = $js_asset['browsers'];

      // Element properties that depend on item type.
      switch ($js_asset['type']) {
        case 'setting':
          $element['#value_prefix'] = $embed_prefix;
          $element['#value'] = 'var drupalSettings = ' . drupal_json_encode(drupal_merge_js_settings($js_asset['data'])) . ";";
          $element['#value_suffix'] = $embed_suffix;
          break;

        case 'inline':
          $element['#value_prefix'] = $embed_prefix;
          $element['#value'] = $js_asset['data'];
          $element['#value_suffix'] = $embed_suffix;
          break;

        case 'file':
          $query_string = empty($js_asset['version']) ? $default_query_string : $js_version_string . $js_asset['version'];
          $query_string_separator = (strpos($js_asset['data'], '?') !== FALSE) ? '&' : '?';
          $element['#attributes']['src'] = file_create_url($js_asset['data']);
          // Only add the cache-busting query string if this isn't an aggregate
          // file.
          if (!isset($js_asset['preprocessed'])) {
            $element['#attributes']['src'] .= $query_string_separator . ($js_asset['cache'] ? $query_string : REQUEST_TIME);
          }
          break;

        case 'external':
          $element['#attributes']['src'] = $js_asset['data'];
          break;

        default:
          throw new \Exception('Invalid JS asset type.');
      }

      // Attributes may only be set if this script is output independently.
      if (!empty($element['#attributes']['src']) && !empty($js_asset['attributes'])) {
        $element['#attributes'] += $js_asset['attributes'];
      }

      $elements[] = $element;
    }

    return $elements;
  }

}
