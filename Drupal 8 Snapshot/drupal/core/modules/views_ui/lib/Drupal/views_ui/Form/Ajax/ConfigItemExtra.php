<?php

/**
 * @file
 * Contains \Drupal\views_ui\Form\Ajax\ConfigItemExtra.
 */

namespace Drupal\views_ui\Form\Ajax;

use Drupal\views\ViewStorageInterface;
use Drupal\views\ViewExecutable;

/**
 * Provides a form for configuring extra information for a Views UI item.
 */
class ConfigItemExtra extends ViewsFormBase {

  /**
   * Constucts a new ConfigItemExtra object.
   */
  public function __construct($type = NULL, $id = NULL) {
    $this->setType($type);
    $this->setID($id);
  }

  /**
   * Implements \Drupal\views_ui\Form\Ajax\ViewsFormInterface::getFormKey().
   */
  public function getFormKey() {
    return 'config-item-extra';
  }

  /**
   * Overrides \Drupal\views_ui\Form\Ajax\ViewsFormBase::getForm().
   */
  public function getForm(ViewStorageInterface $view, $display_id, $js, $type = NULL, $id = NULL) {
    $this->setType($type);
    $this->setID($id);
    return parent::getForm($view, $display_id, $js);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'views_ui_config_item_extra_form';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
    $view = &$form_state['view'];
    $display_id = $form_state['display_id'];
    $type = $form_state['type'];
    $id = $form_state['id'];

    $form = array(
      'options' => array(
        '#tree' => true,
        '#theme_wrappers' => array('container'),
        '#attributes' => array('class' => array('scroll')),
      ),
    );
    $executable = $view->getExecutable();
    $executable->setDisplay($display_id);
    $item = $executable->getItem($display_id, $type, $id);

    if ($item) {
      $handler = $executable->display_handler->getHandler($type, $id);
      if (empty($handler)) {
        $form['markup'] = array('#markup' => t("Error: handler for @table > @field doesn't exist!", array('@table' => $item['table'], '@field' => $item['field'])));
      }
      else {
        $handler->init($executable, $executable->display_handler, $item);
        $types = ViewExecutable::viewsHandlerTypes();

        $form['#title'] = t('Configure extra settings for @type %item', array('@type' => $types[$type]['lstitle'], '%item' => $handler->adminLabel()));

        $form['#section'] = $display_id . '-' . $type . '-' . $id;

        // Get form from the handler.
        $handler->buildExtraOptionsForm($form['options'], $form_state);
        $form_state['handler'] = &$handler;
      }

      $view->getStandardButtons($form, $form_state, 'views_ui_config_item_extra_form');
    }
    return $form;
  }

  /**
   * Overrides \Drupal\views_ui\Form\Ajax\ViewsFormBase::validateForm().
   */
  public function validateForm(array &$form, array &$form_state) {
    $form_state['handler']->validateExtraOptionsForm($form['options'], $form_state);
  }

  /**
   * Overrides \Drupal\views_ui\Form\Ajax\ViewsFormBase::submitForm().
   */
  public function submitForm(array &$form, array &$form_state) {
    // Run it through the handler's submit function.
    $form_state['handler']->submitExtraOptionsForm($form['options'], $form_state);
    $item = $form_state['handler']->options;

    // Store the data we're given.
    foreach ($form_state['values']['options'] as $key => $value) {
      $item[$key] = $value;
    }

    // Store the item back on the view
    $form_state['view']->getExecutable()->setItem($form_state['display_id'], $form_state['type'], $form_state['id'], $item);

    // Write to cache
    $form_state['view']->cacheSet();
  }

}
