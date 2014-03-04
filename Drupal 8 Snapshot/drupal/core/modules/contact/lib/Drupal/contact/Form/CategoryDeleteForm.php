<?php

/**
 * @file
 * Contains \Drupal\contact\Form\CategoryDeleteForm.
 */

namespace Drupal\contact\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Builds the form to delete a contact category.
 */
class CategoryDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelPath() {
    return 'admin/structure/contact';
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
  public function submit(array $form, array &$form_state) {
    $this->entity->delete();
    drupal_set_message(t('Category %label has been deleted.', array('%label' => $this->entity->label())));
    watchdog('contact', 'Category %label has been deleted.', array('%label' => $this->entity->label()), WATCHDOG_NOTICE);
    $form_state['redirect'] = 'admin/structure/contact';
  }

}
