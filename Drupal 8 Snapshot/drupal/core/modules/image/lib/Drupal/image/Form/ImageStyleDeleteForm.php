<?php

/**
 * @file
 * Contains \Drupal\image\Form\ImageStyleDeleteForm.
 */

namespace Drupal\image\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Creates a form to delete an image style.
 */
class ImageStyleDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Optionally select a style before deleting %style', array('%style' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelPath() {
    return 'admin/config/media/image-styles';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('If this style is in use on the site, you may select another style to replace it. All images that have been generated for this style will be permanently deleted.');
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    $replacement_styles = array_diff_key(image_style_options(), array($this->entity->id() => ''));
    $form['replacement'] = array(
      '#title' => t('Replacement style'),
      '#type' => 'select',
      '#options' => $replacement_styles,
      '#empty_option' => t('No replacement, just delete'),
    );

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $this->entity->set('replacementID', $form_state['values']['replacement']);
    $this->entity->delete();
    drupal_set_message(t('Style %name was deleted.', array('%name' => $this->entity->label())));
    $form_state['redirect'] = 'admin/config/media/image-styles';
  }

}
