<?php

/**
 * @file
 * Contains \Drupal\filter\FilterFormatEditFormController.
 */

namespace Drupal\filter;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form controller for adding a filter format.
 */
class FilterFormatEditFormController extends FilterFormatFormControllerBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    if (!$this->entity->status()) {
      throw new NotFoundHttpException();
    }

    drupal_set_title($this->entity->label());
    $form = parent::form($form, $form_state);
    $form['roles']['#default_value'] = array_keys(filter_get_roles_by_format($this->entity));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    parent::submit($form, $form_state);
    drupal_set_message(t('The text format %format has been updated.', array('%format' => $this->entity->label())));
    return $this->entity;
  }

}
