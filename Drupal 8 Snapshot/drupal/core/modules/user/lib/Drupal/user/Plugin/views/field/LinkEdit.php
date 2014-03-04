<?php

/**
 * @file
 * Definition of Drupal\user\Plugin\views\field\LinkEdit.
 */

namespace Drupal\user\Plugin\views\field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Annotation\PluginID;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to user edit.
 *
 * @ingroup views_field_handlers
 *
 * @PluginID("user_link_edit")
 */
class LinkEdit extends Link {

  /**
   * {@inheritdoc}
   */
  protected function renderLink(EntityInterface $entity, ResultRow $values) {
    if ($entity && $entity->access('update')) {
      $this->options['alter']['make_link'] = TRUE;

      $text = !empty($this->options['text']) ? $this->options['text'] : t('Edit');

      $uri = $entity->uri();
      $this->options['alter']['path'] = $uri['path'] . '/edit';
      $this->options['alter']['query'] = drupal_get_destination();

      return $text;
    }
  }

}
