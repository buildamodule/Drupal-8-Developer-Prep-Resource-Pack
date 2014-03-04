<?php

/**
 * @file
 * Contains \Drupal\Core\Form\ConfirmFormBase.
 */

namespace Drupal\Core\Form;

use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an generic base class for a confirmation form.
 */
abstract class ConfirmFormBase implements ConfirmFormInterface {

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
    $path = $this->getCancelPath();
    // Prepare cancel link.
    if ($request->query->has('destination')) {
      $options = drupal_parse_url($request->query->get('destination'));
    }
    elseif (is_array($path)) {
      $options = $path;
    }
    else {
      $options = array('path' => $path);
    }

    drupal_set_title($this->getQuestion(), PASS_THROUGH);

    $form['#attributes']['class'][] = 'confirmation';
    $form['description'] = array('#markup' => $this->getDescription());
    $form[$this->getFormName()] = array('#type' => 'hidden', '#value' => 1);

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->getConfirmText(),
    );
    $form['actions']['cancel'] = array(
      '#type' => 'link',
      '#title' => $this->getCancelText(),
      '#href' => $options['path'],
      '#options' => $options,
    );
    // By default, render the form using theme_confirm_form().
    if (!isset($form['#theme'])) {
      $form['#theme'] = 'confirm_form';
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
  }

}
