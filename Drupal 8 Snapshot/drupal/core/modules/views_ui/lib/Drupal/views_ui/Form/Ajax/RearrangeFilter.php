<?php

/**
 * @file
 * Contains \Drupal\views_ui\Form\Ajax\RearrangeFilter.
 */

namespace Drupal\views_ui\Form\Ajax;

use Drupal\views_ui\ViewUI;
use Drupal\views\ViewExecutable;

/**
 * Provides a rearrange form for Views filters.
 */
class RearrangeFilter extends ViewsFormBase {

  /**
   * Implements \Drupal\views_ui\Form\Ajax\ViewsFormInterface::getFormKey().
   */
  public function getFormKey() {
    return 'rearrange-filter';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'views_ui_rearrange_filter_form';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
    $view = &$form_state['view'];
    $display_id = $form_state['display_id'];
    $type = 'filter';

    $types = ViewExecutable::viewsHandlerTypes();
    $executable = $view->getExecutable();
    if (!$executable->setDisplay($display_id)) {
      views_ajax_render(t('Invalid display id @display', array('@display' => $display_id)));
    }
    $display = $executable->displayHandlers->get($display_id);
    $form['#title'] = check_plain($display->display['display_title']) . ': ';
    $form['#title'] .= t('Rearrange @type', array('@type' => $types[$type]['ltitle']));
    $form['#section'] = $display_id . 'rearrange-item';

    if ($display->defaultableSections($types[$type]['plural'])) {
      $form_state['section'] = $types[$type]['plural'];
      views_ui_standard_display_dropdown($form, $form_state, $form_state['section']);
    }

    if (!empty($view->form_cache)) {
      $groups = $view->form_cache['groups'];
      $handlers = $view->form_cache['handlers'];
    }
    else {
      $groups = $display->getOption('filter_groups');
      $handlers = $display->getOption($types[$type]['plural']);
    }
    $count = 0;

    // Get relationship labels
    $relationships = array();
    foreach ($display->getHandlers('relationship') as $id => $handler) {
      $relationships[$id] = $handler->adminLabel();
    }

    $group_options = array();

    /**
     * Filter groups is an array that contains:
     * array(
     *   'operator' => 'and' || 'or',
     *   'groups' => array(
     *     $group_id => 'and' || 'or',
     *   ),
     * );
     */

    $grouping = count(array_keys($groups['groups'])) > 1;

    $form['filter_groups']['#tree'] = TRUE;
    $form['filter_groups']['operator'] = array(
      '#type' => 'select',
      '#options' => array(
        'AND' => t('And'),
        'OR' => t('Or'),
      ),
      '#default_value' => $groups['operator'],
      '#attributes' => array(
        'class' => array('warning-on-change'),
      ),
      '#title' => t('Operator to use on all groups'),
      '#description' => t('Either "group 0 AND group 1 AND group 2" or "group 0 OR group 1 OR group 2", etc'),
      '#access' => $grouping,
    );

    $form['remove_groups']['#tree'] = TRUE;

    foreach ($groups['groups'] as $id => $group) {
      $form['filter_groups']['groups'][$id] = array(
        '#title' => t('Operator'),
        '#type' => 'select',
        '#options' => array(
          'AND' => t('And'),
          'OR' => t('Or'),
        ),
        '#default_value' => $group,
        '#attributes' => array(
          'class' => array('warning-on-change'),
        ),
      );

      $form['remove_groups'][$id] = array(); // to prevent a notice
      if ($id != 1) {
        $form['remove_groups'][$id] = array(
          '#type' => 'submit',
          '#value' => t('Remove group @group', array('@group' => $id)),
          '#id' => "views-remove-group-$id",
          '#attributes' => array(
            'class' => array('views-remove-group'),
          ),
          '#group' => $id,
        );
      }
      $group_options[$id] = $id == 1 ? t('Default group') : t('Group @group', array('@group' => $id));
      $form['#group_renders'][$id] = array();
    }

    $form['#group_options'] = $group_options;
    $form['#groups'] = $groups;
    // We don't use getHandlers() because we want items without handlers to
    // appear and show up as 'broken' so that the user can see them.
    $form['filters'] = array('#tree' => TRUE);
    foreach ($handlers as $id => $field) {
      // If the group does not exist, move the filters to the default group.
      if (empty($field['group']) || empty($groups['groups'][$field['group']])) {
        $field['group'] = 1;
      }

      $handler = $display->getHandler($type, $id);
      if ($grouping && $handler && !$handler->canGroup()) {
        $field['group'] = 'ungroupable';
      }

      // If not grouping and the handler is set ungroupable, move it back to
      // the default group to prevent weird errors from having it be in its
      // own group:
      if (!$grouping && $field['group'] == 'ungroupable') {
        $field['group'] = 1;
      }

      // Place this item into the proper group for rendering.
      $form['#group_renders'][$field['group']][] = $id;

      $form['filters'][$id]['weight'] = array(
        '#type' => 'textfield',
        '#default_value' => ++$count,
        '#size' => 8,
      );
      $form['filters'][$id]['group'] = array(
        '#type' => 'select',
        '#options' => $group_options,
        '#default_value' => $field['group'],
        '#attributes' => array(
          'class' => array('views-region-select', 'views-region-' . $id),
        ),
        '#access' => $field['group'] !== 'ungroupable',
      );

      if ($handler) {
        $name = $handler->adminLabel() . ' ' . $handler->adminSummary();
        if (!empty($field['relationship']) && !empty($relationships[$field['relationship']])) {
          $name = '(' . $relationships[$field['relationship']] . ') ' . $name;
        }

        $form['filters'][$id]['name'] = array(
          '#markup' => $name,
        );
      }
      else {
        $form['filters'][$id]['name'] = array('#markup' => t('Broken field @id', array('@id' => $id)));
      }
      $form['filters'][$id]['removed'] = array(
        '#type' => 'checkbox',
        '#id' => 'views-removed-' . $id,
        '#attributes' => array('class' => array('views-remove-checkbox')),
        '#default_value' => 0,
      );
    }

    if (isset($form_state['update_name'])) {
      $name = $form_state['update_name'];
    }

    $view->getStandardButtons($form, $form_state, 'views_ui_rearrange_filter_form');
    $form['buttons']['add_group'] = array(
      '#type' => 'submit',
      '#value' => t('Create new filter group'),
      '#id' => 'views-add-group',
      '#group' => 'add',
    );

    return $form;
  }

  /**
   * Overrides \Drupal\views_ui\Form\Ajax\ViewsFormBase::submitForm().
   */
  public function submitForm(array &$form, array &$form_state) {
    $types = ViewExecutable::viewsHandlerTypes();
    $display = &$form_state['view']->getExecutable()->displayHandlers->get($form_state['display_id']);
    $remember_groups = array();

    if (!empty($form_state['view']->form_cache)) {
      $old_fields = $form_state['view']->form_cache['handlers'];
    }
    else {
      $old_fields = $display->getOption($types['filter']['plural']);
    }
    $count = 0;

    $groups = $form_state['values']['filter_groups'];
    // Whatever button was clicked, re-calculate field information.
    $new_fields = $order = array();

    // Make an array with the weights
    foreach ($form_state['values']['filters'] as $field => $info) {
      // add each value that is a field with a weight to our list, but only if
      // it has had its 'removed' checkbox checked.
      if (is_array($info) && empty($info['removed'])) {
        if (isset($info['weight'])) {
          $order[$field] = $info['weight'];
        }

        if (isset($info['group'])) {
          $old_fields[$field]['group'] = $info['group'];
          $remember_groups[$info['group']][] = $field;
        }
      }
    }

    // Sort the array
    asort($order);

    // Create a new list of fields in the new order.
    foreach (array_keys($order) as $field) {
      $new_fields[$field] = $old_fields[$field];
    }

    // If the #group property is set on the clicked button, that means we are
    // either adding or removing a group, not actually updating the filters.
    if (!empty($form_state['clicked_button']['#group'])) {
      if ($form_state['clicked_button']['#group'] == 'add') {
        // Add a new group
        $groups['groups'][] = 'AND';
      }
      else {
        // Renumber groups above the removed one down.
        foreach (array_keys($groups['groups']) as $group_id) {
          if ($group_id >= $form_state['clicked_button']['#group']) {
            $old_group = $group_id + 1;
            if (isset($groups['groups'][$old_group])) {
              $groups['groups'][$group_id] = $groups['groups'][$old_group];
              if (isset($remember_groups[$old_group])) {
                foreach ($remember_groups[$old_group] as $id) {
                  $new_fields[$id]['group'] = $group_id;
                }
              }
            }
            else {
              // If this is the last one, just unset it.
              unset($groups['groups'][$group_id]);
            }
          }
        }
      }
      // Update our cache with values so that cancel still works the way
      // people expect.
      $form_state['view']->form_cache = array(
        'key' => 'rearrange-filter',
        'groups' => $groups,
        'handlers' => $new_fields,
      );

      // Return to this form except on actual Update.
      $form_state['view']->addFormToStack('rearrange-filter', $form_state['display_id'], 'filter');
    }
    else {
      // The actual update button was clicked. Remove the empty groups, and
      // renumber them sequentially.
      ksort($remember_groups);
      $groups['groups'] = static::arrayKeyPlus(array_values(array_intersect_key($groups['groups'], $remember_groups)));
      // Change the 'group' key on each field to match. Here, $mapping is an
      // array whose keys are the old group numbers and whose values are the new
      // (sequentially numbered) ones.
      $mapping = array_flip(static::arrayKeyPlus(array_keys($remember_groups)));
      foreach ($new_fields as &$new_field) {
        $new_field['group'] = $mapping[$new_field['group']];
      }

      // Write the changed handler values.
      $display->setOption($types['filter']['plural'], $new_fields);
      $display->setOption('filter_groups', $groups);
      if (isset($form_state['view']->form_cache)) {
        unset($form_state['view']->form_cache);
      }
    }

    // Store in cache.
    $form_state['view']->cacheSet();
  }

  /**
   * Adds one to each key of an array.
   *
   * For example array(0 => 'foo') would be array(1 => 'foo').
   *
   * @param array
   *   The array to increment keys on.
   *
   * @return array
   *   The array with incremented keys.
   */
  public static function arrayKeyPlus($array) {
    $keys = array_keys($array);
    // Sort the keys in reverse order so incrementing them doesn't overwrite any
    // existing keys.
    rsort($keys);
    foreach ($keys as $key) {
      $array[$key + 1] = $array[$key];
      unset($array[$key]);
    }
    // Sort the keys back to ascending order.
    ksort($array);
    return $array;
  }

}

