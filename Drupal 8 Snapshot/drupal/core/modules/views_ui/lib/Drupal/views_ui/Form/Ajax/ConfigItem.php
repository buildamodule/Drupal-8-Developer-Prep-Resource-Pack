<?php

/**
 * @file
 * Contains \Drupal\views_ui\Form\Ajax\ConfigItem.
 */

namespace Drupal\views_ui\Form\Ajax;

use Drupal\views\ViewStorageInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Provides a form for configuring an item in the Views UI.
 */
class ConfigItem extends ViewsFormBase {

  /**
   * Constucts a new ConfigItem object.
   */
  public function __construct($type = NULL, $id = NULL) {
    $this->setType($type);
    $this->setID($id);
  }

  /**
   * Implements \Drupal\views_ui\Form\Ajax\ViewsFormInterface::getFormKey().
   */
  public function getFormKey() {
    return 'config-item';
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
    return 'views_ui_config_item_form';
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
        '#tree' => TRUE,
        '#theme_wrappers' => array('container'),
        '#attributes' => array('class' => array('scroll')),
      ),
    );
    $executable = $view->getExecutable();
    $save_ui_cache = FALSE;
    $executable->setDisplay($display_id);
    $item = $executable->getItem($display_id, $type, $id);

    if ($item) {
      $handler = $executable->display_handler->getHandler($type, $id);
      if (empty($handler)) {
        $form['markup'] = array('#markup' => t("Error: handler for @table > @field doesn't exist!", array('@table' => $item['table'], '@field' => $item['field'])));
      }
      else {
        $types = ViewExecutable::viewsHandlerTypes();

        // If this item can come from the default display, show a dropdown
        // that lets the user choose which display the changes should apply to.
        if ($executable->display_handler->defaultableSections($types[$type]['plural'])) {
          $form_state['section'] = $types[$type]['plural'];
          views_ui_standard_display_dropdown($form, $form_state, $form_state['section']);
        }

        // A whole bunch of code to figure out what relationships are valid for
        // this item.
        $relationships = $executable->display_handler->getOption('relationships');
        $relationship_options = array();

        foreach ($relationships as $relationship) {
          // relationships can't link back to self. But also, due to ordering,
          // relationships can only link to prior relationships.
          if ($type == 'relationship' && $id == $relationship['id']) {
            break;
          }
          $relationship_handler = Views::handlerManager('relationship')->getHandler($relationship);
          // ignore invalid/broken relationships.
          if (empty($relationship_handler)) {
            continue;
          }

          // If this relationship is valid for this type, add it to the list.
          $data = Views::viewsData()->get($relationship['table']);
          $base = $data[$relationship['field']]['relationship']['base'];
          $base_fields = Views::viewsDataHelper()->fetchFields($base, $form_state['type'], $executable->display_handler->useGroupBy());
          if (isset($base_fields[$item['table'] . '.' . $item['field']])) {
            $relationship_handler->init($executable, $executable->display_handler, $relationship);
            $relationship_options[$relationship['id']] = $relationship_handler->adminLabel();
          }
        }

        if (!empty($relationship_options)) {
          // Make sure the existing relationship is even valid. If not, force
          // it to none.
          $base_fields = Views::viewsDataHelper()->fetchFields($view->get('base_table'), $form_state['type'], $executable->display_handler->useGroupBy());
          if (isset($base_fields[$item['table'] . '.' . $item['field']])) {
            $relationship_options = array_merge(array('none' => t('Do not use a relationship')), $relationship_options);
          }
          $rel = empty($item['relationship']) ? 'none' : $item['relationship'];
          if (empty($relationship_options[$rel])) {
            // Pick the first relationship.
            $rel = key($relationship_options);
            // We want this relationship option to get saved even if the user
            // skips submitting the form.
            $executable->setItemOption($display_id, $type, $id, 'relationship', $rel);
            $save_ui_cache = TRUE;
          }

          $form['options']['relationship'] = array(
            '#type' => 'select',
            '#title' => t('Relationship'),
            '#options' => $relationship_options,
            '#default_value' => $rel,
            '#weight' => -500,
          );
        }
        else {
          $form['options']['relationship'] = array(
            '#type' => 'value',
            '#value' => 'none',
          );
        }

        $form['#title'] = t('Configure @type: @item', array('@type' => $types[$type]['lstitle'], '@item' => $handler->adminLabel()));

        if (!empty($handler->definition['help'])) {
          $form['options']['form_description'] = array(
            '#markup' => $handler->definition['help'],
            '#theme_wrappers' => array('container'),
            '#attributes' => array('class' => array('form-item description')),
            '#weight' => -1000,
          );
        }

        $form['#section'] = $display_id . '-' . $type . '-' . $id;

        // Get form from the handler.
        $handler->buildOptionsForm($form['options'], $form_state);
        $form_state['handler'] = &$handler;
      }

      $name = NULL;
      if (isset($form_state['update_name'])) {
        $name = $form_state['update_name'];
      }

      $view->getStandardButtons($form, $form_state, 'views_ui_config_item_form', $name);
      // Add a 'remove' button.
      $form['buttons']['remove'] = array(
        '#type' => 'submit',
        '#value' => t('Remove'),
        '#submit' => array(array($this, 'remove')),
        '#limit_validation_errors' => array(array('override')),
      );
    }

    if ($save_ui_cache) {
      $view->cacheSet();
    }

    return $form;
  }

  /**
   * Overrides \Drupal\views_ui\Form\Ajax\ViewsFormBase::validateForm().
   */
  public function validateForm(array &$form, array &$form_state) {
    $form_state['handler']->validateOptionsForm($form['options'], $form_state);

    if (form_get_errors()) {
      $form_state['rerender'] = TRUE;
    }
  }

  /**
   * Overrides \Drupal\views_ui\Form\Ajax\ViewsFormBase::submitForm().
   */
  public function submitForm(array &$form, array &$form_state) {
    // Run it through the handler's submit function.
    $form_state['handler']->submitOptionsForm($form['options'], $form_state);
    $item = $form_state['handler']->options;
    $types = ViewExecutable::viewsHandlerTypes();

    // For footer/header $handler_type is area but $type is footer/header.
    // For all other handle types it's the same.
    $handler_type = $type = $form_state['type'];
    if (!empty($types[$type]['type'])) {
      $handler_type = $types[$type]['type'];
    }

    $override = NULL;
    $executable = $form_state['view']->getExecutable();
    if ($executable->display_handler->useGroupBy() && !empty($item['group_type'])) {
      if (empty($executable->query)) {
        $executable->initQuery();
      }
      $aggregate = $executable->query->getAggregationInfo();
      if (!empty($aggregate[$item['group_type']]['handler'][$type])) {
        $override = $aggregate[$item['group_type']]['handler'][$type];
      }
    }

    // Create a new handler and unpack the options from the form onto it. We
    // can use that for storage.
    $handler = Views::handlerManager($handler_type)->getHandler($item, $override);
    $handler->init($executable, $executable->display_handler, $item);

    // Add the incoming options to existing options because items using
    // the extra form may not have everything in the form here.
    $options = $form_state['values']['options'] + $form_state['handler']->options;

    // This unpacks only options that are in the definition, ensuring random
    // extra stuff on the form is not sent through.
    $handler->unpackOptions($handler->options, $options, NULL, FALSE);

    // Store the item back on the view
    $executable->setItem($form_state['display_id'], $form_state['type'], $form_state['id'], $handler->options);

    // Ensure any temporary options are removed.
    if (isset($form_state['view']->temporary_options[$type][$form_state['id']])) {
      unset($form_state['view']->temporary_options[$type][$form_state['id']]);
    }

    // Write to cache
    $form_state['view']->cacheSet();
  }

  /**
   * Submit handler for removing an item from a view
   */
  public function remove(&$form, &$form_state) {
    // Store the item back on the view
    list($was_defaulted, $is_defaulted) = $form_state['view']->getOverrideValues($form, $form_state);
    $executable = $form_state['view']->getExecutable();
    // If the display selection was changed toggle the override value.
    if ($was_defaulted != $is_defaulted) {
      $display = &$executable->displayHandlers->get($form_state['display_id']);
      $display->optionsOverride($form, $form_state);
    }
    $executable->removeItem($form_state['display_id'], $form_state['type'], $form_state['id']);

    // Write to cache
    $form_state['view']->cacheSet();
  }

}
