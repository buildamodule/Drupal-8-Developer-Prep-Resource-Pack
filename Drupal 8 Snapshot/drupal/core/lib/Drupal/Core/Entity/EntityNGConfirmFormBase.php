<?php

/**
 * @file
 * Contains \Drupal\Core\Entity\EntityNGConfirmFormBase.
 */

namespace Drupal\Core\Entity;

use Drupal\Core\Form\ConfirmFormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a generic base class for an entity-based confirmation form.
 */
abstract class EntityNGConfirmFormBase extends EntityFormControllerNG implements ConfirmFormInterface {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function getBaseFormID() {
    return $this->entity->entityType() . '_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Confirm');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormName() {
    return 'confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, Request $request = NULL) {
    $this->request = $request;
    $form = parent::buildForm($form, $form_state);

    $form['#attributes']['class'][] = 'confirmation';
    $form['description'] = array('#markup' => $this->getDescription());
    $form[$this->getFormName()] = array('#type' => 'hidden', '#value' => 1);

    // By default, render the form using theme_confirm_form().
    if (!isset($form['#theme'])) {
      $form['#theme'] = 'confirm_form';
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function init(array &$form_state) {
    parent::init($form_state);

    drupal_set_title($this->getQuestion(), PASS_THROUGH);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, array &$form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->getConfirmText();
    unset($actions['delete']);

    $path = $this->getCancelPath();
    // Prepare cancel link.
    if ($this->request->query->has('destination')) {
      $options = drupal_parse_url($this->request->query->get('destination'));
    }
    elseif (is_array($path)) {
      $options = $path;
    }
    else {
      $options = array('path' => $path);
    }
    $actions['cancel'] = array(
      '#type' => 'link',
      '#title' => $this->getCancelText(),
      '#href' => $options['path'],
      '#options' => $options,
    );
    return $actions;
  }

}
