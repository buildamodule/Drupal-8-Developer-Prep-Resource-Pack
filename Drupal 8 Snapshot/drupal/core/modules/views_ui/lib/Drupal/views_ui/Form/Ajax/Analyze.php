<?php

/**
 * @file
 * Contains \Drupal\views_ui\Form\Ajax\Analyze.
 */

namespace Drupal\views_ui\Form\Ajax;

use Drupal\views\Views;
use Drupal\views_ui\ViewUI;
use Drupal\views\Analyzer;

/**
 * Displays analysis information for a view.
 */
class Analyze extends ViewsFormBase {

  /**
   * Implements \Drupal\views_ui\Form\Ajax\ViewsFormInterface::getFormKey().
   */
  public function getFormKey() {
    return 'analyze';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'views_ui_analyze_view_form';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
    $view = &$form_state['view'];

    $form['#title'] = t('View analysis');
    $form['#section'] = 'analyze';

    $analyzer = Views::analyzer();
    $messages = $analyzer->getMessages($view->getExecutable());

    $form['analysis'] = array(
      '#prefix' => '<div class="form-item">',
      '#suffix' => '</div>',
      '#markup' => $analyzer->formatMessages($messages),
    );

    // Inform the standard button function that we want an OK button.
    $form_state['ok_button'] = TRUE;
    $view->getStandardButtons($form, $form_state, 'views_ui_analyze_view_form');
    return $form;
  }

  /**
   * Overrides \Drupal\views_ui\Form\Ajax\ViewsFormBase::submitForm().
   */
  public function submitForm(array &$form, array &$form_state) {
    $form_state['redirect'] = 'admin/structure/views/view/' . $form_state['view']->id() . '/edit';
  }

}
