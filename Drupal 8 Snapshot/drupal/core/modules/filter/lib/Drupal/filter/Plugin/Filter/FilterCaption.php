<?php

/**
 * @file
 * Contains \Drupal\filter\Plugin\Filter\FilterCaption.
 */

namespace Drupal\filter\Plugin\Filter;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Annotation\Translation;
use Drupal\filter\Annotation\Filter;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to display image captions and align images.
 *
 * @Filter(
 *   id = "filter_caption",
 *   module = "filter",
 *   title = @Translation("Display image captions and align images"),
 *   description = @Translation("Uses data-caption and data-align attributes on &lt;img&gt; tags to caption and align images."),
 *   type = FILTER_TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class FilterCaption extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode, $cache, $cache_id) {
    $search = array();
    $replace = array();

    if (stristr($text, 'data-caption') !== FALSE || stristr($text, 'data-align') !== FALSE) {
      $dom = filter_dom_load($text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query('//*[@data-caption or @data-align]') as $node) {
        $caption = NULL;
        $align = NULL;

        // Retrieve, then remove the data-caption and data-align attributes.
        if ($node->hasAttribute('data-caption')) {
          $caption = String::checkPlain($node->getAttribute('data-caption'));
          $node->removeAttribute('data-caption');
          // Sanitize caption: decode HTML encoding, limit allowed HTML tags.
          $caption = String::decodeEntities($caption);
          $caption = Xss::filter($caption);
          // The caption must be non-empty.
          if (Unicode::strlen($caption) === 0) {
            $caption = NULL;
          }
        }
        if ($node->hasAttribute('data-align')) {
          $align = $node->getAttribute('data-align');
          $node->removeAttribute('data-align');
          // Only allow 3 values: 'left', 'center' and 'right'.
          if (!in_array($align, array('left', 'center', 'right'))) {
            $align = NULL;
          }
        }

        // If neither attribute has a value after validation, then don't
        // transform the HTML.
        if ($caption === NULL && $align === NULL) {
          continue;
        }

        // Given the updated node, caption and alignment: re-render it with a
        // caption.
        $filter_caption = array(
          '#theme' => 'filter_caption',
          '#node' => $node->C14N(),
          '#tag' => $node->tagName,
          '#caption' => $caption,
          '#align' => $align,
        );
        $altered_html = drupal_render($filter_caption);

        // Load the altered HTML into a new DOMDocument and retrieve the element.
        $updated_node = filter_dom_load($altered_html)->getElementsByTagName('body')
          ->item(0)
          ->childNodes
          ->item(0);

        // Import the updated node from the new DOMDocument into the original
        // one, importing also the child nodes of the updated node.
        $updated_node = $dom->importNode($updated_node, TRUE);
        // Finally, replace the original image node with the new image node!
        $node->parentNode->replaceChild($updated_node, $node);
      }

      return filter_dom_serialize($dom);
    }

    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return t('
        <p>You can add image captions and align images left, right or centered. Examples:</p>
        <ul>
          <li>Caption an image: <code>&lt;img src="" data-caption="This is a caption" /&gt;</code></li>
          <li>Align an image: <code>&lt;img src="" data-align="center" /&gt;</code></li>
          <li>Caption & align an image: <code>&lt;img src="" data-caption="Alpaca" data-align="right" /&gt;</code></li>
        </ul>');
    }
    else {
      return t('You can caption (data-caption="Text") and align images (data-align="center"), but also video, blockquotes, and so on.');
    }
  }
}
