<?php

/**
 * @file
 * Contains \Drupal\taxonomy\Form\VocabularyDeleteForm.
 */

namespace Drupal\taxonomy\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Cache\Cache;

/**
 * Provides a deletion confirmation form for taxonomy vocabulary.
 */
class VocabularyDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'taxonomy_vocabulary_confirm_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the vocabulary %title?', array('%title' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelPath() {
    return 'admin/structure/taxonomy';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('Deleting a vocabulary will delete all the terms in it. This action cannot be undone.');
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
    drupal_set_message(t('Deleted vocabulary %name.', array('%name' => $this->entity->label())));
    watchdog('taxonomy', 'Deleted vocabulary %name.', array('%name' => $this->entity->label()), WATCHDOG_NOTICE);
    $form_state['redirect'] = 'admin/structure/taxonomy';
    Cache::invalidateTags(array('content' => TRUE));
  }

}
