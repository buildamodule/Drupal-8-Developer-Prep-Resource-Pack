<?php

/**
 * @file
 * Contains \Drupal\taxonomy\Controller\TaxonomyController.
 */

namespace Drupal\taxonomy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route responses for taxonomy.module.
 */
class TaxonomyController extends ControllerBase {

  /**
   * Returns a rendered edit form to create a new term associated to the given vocabulary.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $taxonomy_vocabulary
   *   The vocabulary this term will be added to.
   *
   * @return array
   *   The taxonomy term add form.
   */
  public function addForm(VocabularyInterface $taxonomy_vocabulary) {
    $term = $this->entityManager()->getStorageController('taxonomy_term')->create(array('vid' => $taxonomy_vocabulary->id()));
    if ($this->moduleHandler()->moduleExists('language')) {
      $term->langcode = language_get_default_langcode('taxonomy_term', $taxonomy_vocabulary->id());
    }
    return $this->entityManager()->getForm($term);
  }

}
