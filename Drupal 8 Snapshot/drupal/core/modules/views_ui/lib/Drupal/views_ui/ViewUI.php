<?php

/**
 * @file
 * Definition of Drupal\views_ui\ViewUI.
 */

namespace Drupal\views_ui;

use Drupal\views\Views;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\views\ViewExecutable;
use Drupal\Core\Database\Database;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\Core\Entity\View;
use Drupal\views\ViewStorageInterface;

/**
 * Stores UI related temporary settings.
 */
class ViewUI implements ViewStorageInterface {

  /**
   * Indicates if a view is currently being edited.
   *
   * @var bool
   */
  public $editing = FALSE;

  /**
   * Stores an array of displays that have been changed.
   *
   * @var array
   */
  public $changed_display;

  /**
   * How long the view takes to build.
   *
   * @var int
   */
  public $build_time;

  /**
   * How long the view takes to render.
   *
   * @var int
   */
  public $render_time;

  /**
   * How long the view takes to execute.
   *
   * @var int
   */
  public $execute_time;

  /**
   * If this view is locked for editing.
   *
   * If this view is locked it will contain the result of
   * \Drupal\user\TempStore::getMetadata(). Which can be a stdClass or NULL.
   *
   * @var stdClass
   */
  public $lock;

  /**
   * If this view has been changed.
   *
   * @var bool
   */
  public $changed;

  /**
   * Stores options temporarily while editing.
   *
   * @var array
   */
  public $temporary_options;

  /**
   * Stores a stack of UI forms to display.
   *
   * @var array
   */
  public $stack;

  /**
   * Is the view runned in a context of the preview in the admin interface.
   *
   * @var bool
   */
  public $live_preview;

  public $renderPreview = FALSE;

  /**
   * The View storage object.
   *
   * @var \Drupal\views\Plugin\Core\Entity\View
   */
  protected $storage;

  /**
   * The View executable object.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $executable;

  /**
   * Stores a list of database queries run beside the main one from views.
   *
   * @var array
   *
   * @see \Drupal\Core\Database\Log
   */
  protected $additionalQueries;

  /**
   * Contains an array of form keys and their respective classes.
   *
   * @var array
   */
  public static $forms = array(
    'add-item' => '\Drupal\views_ui\Form\Ajax\AddItem',
    'analyze' => '\Drupal\views_ui\Form\Ajax\Analyze',
    'config-item' => '\Drupal\views_ui\Form\Ajax\ConfigItem',
    'config-item-extra' => '\Drupal\views_ui\Form\Ajax\ConfigItemExtra',
    'config-item-group' => '\Drupal\views_ui\Form\Ajax\ConfigItemGroup',
    'display' => '\Drupal\views_ui\Form\Ajax\Display',
    'edit-details' => '\Drupal\views_ui\Form\Ajax\EditDetails',
    'rearrange' => '\Drupal\views_ui\Form\Ajax\Rearrange',
    'rearrange-filter' => '\Drupal\views_ui\Form\Ajax\RearrangeFilter',
    'reorder-displays' => '\Drupal\views_ui\Form\Ajax\ReorderDisplays',
  );

  /**
   * Constructs a View UI object.
   *
   * @param \Drupal\views\ViewStorageInterface $storage
   *   The View storage object to wrap.
   */
  public function __construct(ViewStorageInterface $storage, ViewExecutable $executable = NULL) {
    $this->entityType = 'view';
    $this->storage = $storage;
    if (!isset($executable)) {
      $executable = Views::executableFactory()->get($this);
    }
    $this->executable = $executable;
  }

  /**
   * Overrides \Drupal\Core\Config\Entity\ConfigEntityBase::get().
   */
  public function get($property_name, $langcode = NULL) {
    if (property_exists($this->storage, $property_name)) {
      return $this->storage->get($property_name, $langcode);
    }

    return isset($this->{$property_name}) ? $this->{$property_name} : NULL;
  }

  /**
   * Implements \Drupal\Core\Config\Entity\ConfigEntityInterface::setStatus().
   */
  public function setStatus($status) {
    return $this->storage->setStatus($status);
  }

  /**
   * Overrides \Drupal\Core\Config\Entity\ConfigEntityBase::set().
   */
  public function set($property_name, $value, $notify = TRUE) {
    if (property_exists($this->storage, $property_name)) {
      $this->storage->set($property_name, $value);
    }
    else {
      $this->{$property_name} = $value;
    }
  }

  public static function getDefaultAJAXMessage() {
    return '<div class="message">' . t("Click on an item to edit that item's details.") . '</div>';
  }

  /**
   * Basic submit handler applicable to all 'standard' forms.
   *
   * This submit handler determines whether the user wants the submitted changes
   * to apply to the default display or to the current display, and dispatches
   * control appropriately.
   */
  public function standardSubmit($form, &$form_state) {
    // Determine whether the values the user entered are intended to apply to
    // the current display or the default display.

    list($was_defaulted, $is_defaulted, $revert) = $this->getOverrideValues($form, $form_state);

    // Based on the user's choice in the display dropdown, determine which display
    // these changes apply to.
    if ($revert) {
      // If it's revert just change the override and return.
      $display = &$this->executable->displayHandlers->get($form_state['display_id']);
      $display->optionsOverride($form, $form_state);

      // Don't execute the normal submit handling but still store the changed view into cache.
      $this->cacheSet();
      return;
    }
    elseif ($was_defaulted === $is_defaulted) {
      // We're not changing which display these form values apply to.
      // Run the regular submit handler for this form.
    }
    elseif ($was_defaulted && !$is_defaulted) {
      // We were using the default display's values, but we're now overriding
      // the default display and saving values specific to this display.
      $display = &$this->executable->displayHandlers->get($form_state['display_id']);
      // optionsOverride toggles the override of this section.
      $display->optionsOverride($form, $form_state);
      $display->submitOptionsForm($form, $form_state);
    }
    elseif (!$was_defaulted && $is_defaulted) {
      // We used to have an override for this display, but the user now wants
      // to go back to the default display.
      // Overwrite the default display with the current form values, and make
      // the current display use the new default values.
      $display = &$this->executable->displayHandlers->get($form_state['display_id']);
      // optionsOverride toggles the override of this section.
      $display->optionsOverride($form, $form_state);
      $display->submitOptionsForm($form, $form_state);
    }

    $submit_handler = $form['#form_id'] . '_submit';
    if (isset($form_state['build_info']['callback_object'])) {
      $submit_handler = array($form_state['build_info']['callback_object'], 'submitForm');
    }
    if (is_callable($submit_handler)) {
      // The submit handler might be a function or a method on the
      // callback_object. Additional note that we have to pass the parameters
      // by reference, as php 5.4 requires us to do that.
      call_user_func_array($submit_handler, array(&$form, &$form_state));
    }
  }

  /**
   * Submit handler for cancel button
   */
  public function standardCancel($form, &$form_state) {
    if (!empty($this->changed) && isset($this->form_cache)) {
      unset($this->form_cache);
      $this->cacheSet();
    }

    $form_state['redirect'] = 'admin/structure/views/view/' . $this->id() . '/edit';
  }

  /**
   * Provide a standard set of Apply/Cancel/OK buttons for the forms. Also provide
   * a hidden op operator because the forms plugin doesn't seem to properly
   * provide which button was clicked.
   *
   * TODO: Is the hidden op operator still here somewhere, or is that part of the
   * docblock outdated?
   */
  public function getStandardButtons(&$form, &$form_state, $form_id, $name = NULL) {
    $form['buttons'] = array(
      '#prefix' => '<div class="clearfix"><div class="form-buttons">',
      '#suffix' => '</div></div>',
    );

    if (empty($name)) {
      $name = t('Apply');
      if (!empty($this->stack) && count($this->stack) > 1) {
        $name = t('Apply and continue');
      }
      $names = array(t('Apply'), t('Apply and continue'));
    }

    // Forms that are purely informational set an ok_button flag, so we know not
    // to create an "Apply" button for them.
    if (empty($form_state['ok_button'])) {
      $form['buttons']['submit'] = array(
        '#type' => 'submit',
        '#value' => $name,
        // The regular submit handler ($form_id . '_submit') does not apply if
        // we're updating the default display. It does apply if we're updating
        // the current display. Since we have no way of knowing at this point
        // which display the user wants to update, views_ui_standard_submit will
        // take care of running the regular submit handler as appropriate.
        '#submit' => array(array($this, 'standardSubmit')),
        '#button_type' => 'primary',
      );
      // Form API button click detection requires the button's #value to be the
      // same between the form build of the initial page request, and the
      // initial form build of the request processing the form submission.
      // Ideally, the button's #value shouldn't change until the form rebuild
      // step. However, \Drupal\views_ui\Form\Ajax\ViewsFormBase::getForm()
      // implements a different multistep form workflow than the Form API does,
      // and adjusts $view->stack prior to form processing, so we compensate by
      // extending button click detection code to support any of the possible
      // button labels.
      if (isset($names)) {
        $form['buttons']['submit']['#values'] = $names;
        $form['buttons']['submit']['#process'] = array_merge(array('views_ui_form_button_was_clicked'), element_info_property($form['buttons']['submit']['#type'], '#process', array()));
      }
      // If a validation handler exists for the form, assign it to this button.
      if (isset($form_state['build_info']['callback_object'])) {
        $form['buttons']['submit']['#validate'][] = array($form_state['build_info']['callback_object'], 'validateForm');
      }
      if (function_exists($form_id . '_validate')) {
        $form['buttons']['submit']['#validate'][] = $form_id . '_validate';
      }
    }

    // Create a "Cancel" button. For purely informational forms, label it "OK".
    $cancel_submit = function_exists($form_id . '_cancel') ? $form_id . '_cancel' : array($this, 'standardCancel');
    $form['buttons']['cancel'] = array(
      '#type' => 'submit',
      '#value' => empty($form_state['ok_button']) ? t('Cancel') : t('Ok'),
      '#submit' => array($cancel_submit),
      '#validate' => array(),
      '#limit_validation_errors' => array(),
    );

    // Compatibility, to be removed later: // TODO: When is "later"?
    // We used to set these items on the form, but now we want them on the $form_state:
    if (isset($form['#title'])) {
      $form_state['title'] = $form['#title'];
    }
    if (isset($form['#url'])) {
      $form_state['url'] = $form['#url'];
    }
    if (isset($form['#section'])) {
      $form_state['#section'] = $form['#section'];
    }
    // Finally, we never want these cached -- our object cache does that for us.
    $form['#no_cache'] = TRUE;

    // If this isn't an ajaxy form, then we want to set the title.
    if (!empty($form['#title'])) {
      drupal_set_title($form['#title']);
    }
  }

  /**
   * Return the was_defaulted, is_defaulted and revert state of a form.
   */
  public function getOverrideValues($form, $form_state) {
    // Make sure the dropdown exists in the first place.
    if (isset($form_state['values']['override']['dropdown'])) {
      // #default_value is used to determine whether it was the default value or not.
      // So the available options are: $display, 'default' and 'default_revert', not 'defaults'.
      $was_defaulted = ($form['override']['dropdown']['#default_value'] === 'defaults');
      $is_defaulted = ($form_state['values']['override']['dropdown'] === 'default');
      $revert = ($form_state['values']['override']['dropdown'] === 'default_revert');

      if ($was_defaulted !== $is_defaulted && isset($form['#section'])) {
        // We're changing which display these values apply to.
        // Update the #section so it knows what to mark changed.
        $form['#section'] = str_replace('default-', $form_state['display_id'] . '-', $form['#section']);
      }
    }
    else {
      // The user didn't get the dropdown for overriding the default display.
      $was_defaulted = FALSE;
      $is_defaulted = FALSE;
      $revert = FALSE;
    }

    return array($was_defaulted, $is_defaulted, $revert);
  }

  /**
   * Add another form to the stack; clicking 'apply' will go to this form
   * rather than closing the ajax popup.
   */
  public function addFormToStack($key, $display_id, $type, $id = NULL, $top = FALSE, $rebuild_keys = FALSE) {
    // Reset the cache of IDs. Drupal rather aggressively prevents ID
    // duplication but this causes it to remember IDs that are no longer even
    // being used.
    $seen_ids_init = &drupal_static('drupal_html_id:init');
    $seen_ids_init = array();

    if (empty($this->stack)) {
      $this->stack = array();
    }

    $stack = array(implode('-', array_filter(array($key, $this->id(), $display_id, $type, $id))), $key, $display_id, $type, $id);
    // If we're being asked to add this form to the bottom of the stack, no
    // special logic is required. Our work is equally easy if we were asked to add
    // to the top of the stack, but there's nothing in it yet.
    if (!$top || empty($this->stack)) {
      $this->stack[] = $stack;
    }
    // If we're adding to the top of an existing stack, we have to maintain the
    // existing integer keys, so they can be used for the "2 of 3" progress
    // indicator (which will now read "2 of 4").
    else {
      $keys = array_keys($this->stack);
      $first = current($keys);
      $last = end($keys);
      for ($i = $last; $i >= $first; $i--) {
        if (!isset($this->stack[$i])) {
          continue;
        }
        // Move form number $i to the next position in the stack.
        $this->stack[$i + 1] = $this->stack[$i];
        unset($this->stack[$i]);
      }
      // Now that the previously $first slot is free, move the new form into it.
      $this->stack[$first] = $stack;
      ksort($this->stack);

      // Start the keys from 0 again, if requested.
      if ($rebuild_keys) {
        $this->stack = array_values($this->stack);
      }
    }
  }

  /**
   * Submit handler for adding new item(s) to a view.
   */
  public function submitItemAdd($form, &$form_state) {
    $type = $form_state['type'];
    $types = ViewExecutable::viewsHandlerTypes();
    $section = $types[$type]['plural'];

    // Handle the override select.
    list($was_defaulted, $is_defaulted) = $this->getOverrideValues($form, $form_state);
    if ($was_defaulted && !$is_defaulted) {
      // We were using the default display's values, but we're now overriding
      // the default display and saving values specific to this display.
      $display = &$this->executable->displayHandlers->get($form_state['display_id']);
      // setOverride toggles the override of this section.
      $display->setOverride($section);
    }
    elseif (!$was_defaulted && $is_defaulted) {
      // We used to have an override for this display, but the user now wants
      // to go back to the default display.
      // Overwrite the default display with the current form values, and make
      // the current display use the new default values.
      $display = &$this->executable->displayHandlers->get($form_state['display_id']);
      // optionsOverride toggles the override of this section.
      $display->setOverride($section);
    }

    if (!empty($form_state['values']['name']) && is_array($form_state['values']['name'])) {
      // Loop through each of the items that were checked and add them to the view.
      foreach (array_keys(array_filter($form_state['values']['name'])) as $field) {
        list($table, $field) = explode('.', $field, 2);

        if ($cut = strpos($field, '$')) {
          $field = substr($field, 0, $cut);
        }
        $id = $this->executable->addItem($form_state['display_id'], $type, $table, $field);

        // check to see if we have group by settings
        $key = $type;
        // Footer,header and empty text have a different internal handler type(area).
        if (isset($types[$type]['type'])) {
          $key = $types[$type]['type'];
        }
        $item = array(
          'table' => $table,
          'field' => $field,
        );
        $handler = Views::handlerManager($key)->getHandler($item);
        if ($this->executable->displayHandlers->get('default')->useGroupBy() && $handler->usesGroupBy()) {
          $this->addFormToStack('config-item-group', $form_state['display_id'], $type, $id);
        }

        // check to see if this type has settings, if so add the settings form first
        if ($handler && $handler->hasExtraOptions()) {
          $this->addFormToStack('config-item-extra', $form_state['display_id'], $type, $id);
        }
        // Then add the form to the stack
        $this->addFormToStack('config-item', $form_state['display_id'], $type, $id);
      }
    }

    if (isset($this->form_cache)) {
      unset($this->form_cache);
    }

    // Store in cache
    $this->cacheSet();
  }

  /**
   * Set up query capturing.
   *
   * \Drupal\Core\Database\Database stores the queries that it runs, if logging
   * is enabled.
   *
   * @see ViewUI::endQueryCapture()
   */
  public function startQueryCapture() {
    Database::startLog('views');
  }

  /**
   * Add the list of queries run during render to buildinfo.
   *
   * @see ViewUI::startQueryCapture()
   */
  public function endQueryCapture() {
    $queries = Database::getLog('views');

    $this->additionalQueries = $queries;
  }

  public function renderPreview($display_id, $args = array()) {
    // Save the current path so it can be restored before returning from this function.
    $old_q = current_path();

    // Determine where the query and performance statistics should be output.
    $config = \Drupal::config('views.settings');
    $show_query = $config->get('ui.show.sql_query.enabled');
    $show_info = $config->get('ui.show.preview_information');
    $show_location = $config->get('ui.show.sql_query.where');

    $show_stats = $config->get('ui.show.performance_statistics');
    if ($show_stats) {
      $show_stats = $config->get('ui.show.sql_query.where');
    }

    $combined = $show_query && $show_stats;

    $rows = array('query' => array(), 'statistics' => array());
    $output = '';

    $errors = $this->executable->validate();
    $this->executable->destroy();
    if (empty($errors)) {
      $this->ajax = TRUE;
      $this->executable->live_preview = TRUE;
      $this->views_ui_context = TRUE;

      // AJAX happens via HTTP POST but everything expects exposed data to
      // be in GET. Copy stuff but remove ajax-framework specific keys.
      // If we're clicking on links in a preview, though, we could actually
      // have some input in the query parameters, so we merge request() and
      // query() to ensure we get it all.
      $exposed_input = array_merge(\Drupal::request()->request->all(), \Drupal::request()->query->all());
      foreach (array('view_name', 'view_display_id', 'view_args', 'view_path', 'view_dom_id', 'pager_element', 'view_base_path', 'ajax_html_ids', 'ajax_page_state', 'form_id', 'form_build_id', 'form_token') as $key) {
        if (isset($exposed_input[$key])) {
          unset($exposed_input[$key]);
        }
      }
      $this->executable->setExposedInput($exposed_input);

      if (!$this->executable->setDisplay($display_id)) {
        return t('Invalid display id @display', array('@display' => $display_id));
      }

      $this->executable->setArguments($args);

      // Store the current view URL for later use:
      if ($this->executable->display_handler->getOption('path')) {
        $path = $this->executable->getUrl();
      }

      // Make view links come back to preview.
      $this->override_path = 'admin/structure/views/view/' . $this->id() . '/preview/' . $display_id;

      // Also override the current path so we get the pager.
      $original_path = current_path();
      $q = _current_path($this->override_path);
      if ($args) {
        $q .= '/' . implode('/', $args);
        _current_path($q);
      }

      // Suppress contextual links of entities within the result set during a
      // Preview.
      // @todo We'll want to add contextual links specific to editing the View, so
      //   the suppression may need to be moved deeper into the Preview pipeline.
      views_ui_contextual_links_suppress_push();

      $show_additional_queries = $config->get('ui.show.additional_queries');

      timer_start('views_ui.preview');

      if ($show_additional_queries) {
        $this->startQueryCapture();
      }

      // Execute/get the view preview.
      $preview = $this->executable->preview($display_id, $args);
      $preview = drupal_render($preview);

      if ($show_additional_queries) {
        $this->endQueryCapture();
      }

      $this->render_time = timer_stop('views_ui.preview');

      views_ui_contextual_links_suppress_pop();

      // Reset variables.
      unset($this->override_path);
      _current_path($original_path);

      // Prepare the query information and statistics to show either above or
      // below the view preview.
      if ($show_info || $show_query || $show_stats) {
        // Get information from the preview for display.
        if (!empty($this->executable->build_info['query'])) {
          if ($show_query) {
            $query_string = $this->executable->build_info['query'];
            // Only the sql default class has a method getArguments.
            $quoted = array();

            if ($this->executable->query instanceof Sql) {
              $quoted = $query_string->getArguments();
              $connection = Database::getConnection();
              foreach ($quoted as $key => $val) {
                if (is_array($val)) {
                  $quoted[$key] = implode(', ', array_map(array($connection, 'quote'), $val));
                }
                else {
                  $quoted[$key] = $connection->quote($val);
                }
              }
            }
            $rows['query'][] = array('<strong>' . t('Query') . '</strong>', '<pre>' . check_plain(strtr($query_string, $quoted)) . '</pre>');
            if (!empty($this->additionalQueries)) {
              $queries = '<strong>' . t('These queries were run during view rendering:') . '</strong>';
              foreach ($this->additionalQueries as $query) {
                if ($queries) {
                  $queries .= "\n";
                }
                $query_string = strtr($query['query'], $query['args']);
                $queries .= t('[@time ms] @query', array('@time' => round($query['time'] * 100000, 1) / 100000.0, '@query' => $query_string));
              }

              $rows['query'][] = array('<strong>' . t('Other queries') . '</strong>', '<pre>' . $queries . '</pre>');
            }
          }
          if ($show_info) {
            $rows['query'][] = array('<strong>' . t('Title') . '</strong>', filter_xss_admin($this->executable->getTitle()));
            if (isset($path)) {
              $path = l($path, $path);
            }
            else {
              $path = t('This display has no path.');
            }
            $rows['query'][] = array('<strong>' . t('Path') . '</strong>', $path);
          }

          if ($show_stats) {
            $rows['statistics'][] = array('<strong>' . t('Query build time') . '</strong>', t('@time ms', array('@time' => intval($this->executable->build_time * 100000) / 100)));
            $rows['statistics'][] = array('<strong>' . t('Query execute time') . '</strong>', t('@time ms', array('@time' => intval($this->executable->execute_time * 100000) / 100)));
            $rows['statistics'][] = array('<strong>' . t('View render time') . '</strong>', t('@time ms', array('@time' => intval($this->executable->render_time * 100000) / 100)));

          }
          \Drupal::moduleHandler()->alter('views_preview_info', $rows, $this);
        }
        else {
          // No query was run. Display that information in place of either the
          // query or the performance statistics, whichever comes first.
          if ($combined || ($show_location === 'above')) {
            $rows['query'] = array(array('<strong>' . t('Query') . '</strong>', t('No query was run')));
          }
          else {
            $rows['statistics'] = array(array('<strong>' . t('Query') . '</strong>', t('No query was run')));
          }
        }
      }
    }
    else {
      foreach ($errors as $display_errors) {
        foreach ($display_errors as $error) {
          drupal_set_message($error, 'error');
        }
      }
      $preview = t('Unable to preview due to validation errors.');
    }

    // Assemble the preview, the query info, and the query statistics in the
    // requested order.
    $table = array(
      '#theme' => 'table',
      '#prefix' => '<div class="views-query-info">',
      '#suffix' => '</div>',
    );
    if ($show_location === 'above' || $show_location === 'below') {
      if ($combined) {
        $table['#rows'] = array_merge($rows['query'], $rows['statistics']);
      }
      else {
        $table['#rows'] = $rows['query'];
      }
    }
    elseif ($show_stats === 'above' || $show_stats === 'below') {
      $table['#rows'] = $rows['statistics'];
    }

    if ($show_location === 'above' || $show_stats === 'above') {
      $output .= drupal_render($table) . $preview;
    }
    elseif ($show_location === 'below' || $show_stats === 'below') {
      $output .= $preview . drupal_render($table);
    }

    _current_path($old_q);
    return $output;
  }

  /**
   * Get the user's current progress through the form stack.
   *
   * @return
   *   FALSE if the user is not currently in a multiple-form stack. Otherwise,
   *   an associative array with the following keys:
   *   - current: The number of the current form on the stack.
   *   - total: The total number of forms originally on the stack.
   */
  public function getFormProgress() {
    $progress = FALSE;
    if (!empty($this->stack)) {
      $stack = $this->stack;
      // The forms on the stack have integer keys that don't change as the forms
      // are completed, so we can see which ones are still left.
      $keys = array_keys($this->stack);
      // Add 1 to the array keys for the benefit of humans, who start counting
      // from 1 and not 0.
      $current = reset($keys) + 1;
      $total = end($keys) + 1;
      if ($total > 1) {
        $progress = array();
        $progress['current'] = $current;
        $progress['total'] = $total;
      }
    }
    return $progress;
  }

  /**
   * Sets a cached view object in the user tempstore.
   */
  public function cacheSet() {
    if ($this->isLocked()) {
      drupal_set_message(t('Changes cannot be made to a locked view.'), 'error');
      return;
    }

    // Let any future object know that this view has changed.
    $this->changed = TRUE;

    $executable = $this->getExecutable();
    if (isset($executable->current_display)) {
      // Add the knowledge of the changed display, too.
      $this->changed_display[$executable->current_display] = TRUE;
      unset($executable->current_display);
    }

    // Unset handlers; we don't want to write these into the cache.
    unset($executable->display_handler);
    unset($executable->default_display);
    $executable->query = NULL;
    unset($executable->displayHandlers);
    \Drupal::service('user.tempstore')->get('views')->set($this->id(), $this);
  }

  /**
   * Returns whether the current view is locked.
   *
   * @return bool
   *   TRUE if the view is locked, FALSE otherwise.
   */
  public function isLocked() {
    return is_object($this->lock) && ($this->lock->owner != $GLOBALS['user']->id());
  }

  /**
   * Passes through all unknown calls onto the storage object.
   */
  public function __call($method, $args) {
    return call_user_func_array(array($this->storage, $method), $args);
  }

  /**
   * {@inheritdoc}
   */
  public function &getDisplay($display_id) {
    return $this->storage->getDisplay($display_id);
  }

  /**
   * Implements \IteratorAggregate::getIterator().
   */
  public function getIterator() {
    return $this->storage->getIterator();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::id().
   */
  public function id() {
    return $this->storage->id();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::uuid().
   */
  public function uuid() {
    return $this->storage->uuid();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::isNew().
   */
  public function isNew() {
    return $this->storage->isNew();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::entityType().
   */
  public function entityType() {
    return $this->storage->entityType();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::bundle().
   */
  public function bundle() {
    return $this->storage->bundle();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::isDefaultRevision().
   */
  public function isDefaultRevision($new_value = NULL) {
    return $this->storage->isDefaultRevision($new_value);
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::getRevisionId().
   */
  public function getRevisionId() {
    return $this->storage->getRevisionId();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::entityInfo().
   */
  public function entityInfo() {
    return $this->storage->entityInfo();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::createDuplicate().
   */
  public function createDuplicate() {
    return $this->storage->createDuplicate();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::delete().
   */
  public function delete() {
    return $this->storage->delete();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::save().
   */
  public function save() {
    return $this->storage->save();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::uri().
   */
  public function uri() {
    return $this->storage->uri();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::label().
   */
  public function label($langcode = NULL) {
    return $this->storage->label($langcode);
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::isNewRevision().
   */
  public function isNewRevision() {
    return $this->storage->isNewRevision();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::setNewRevision().
   */
  public function setNewRevision($value = TRUE) {
    return $this->storage->setNewRevision($value);
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::enforceIsNew().
   */
  public function enforceIsNew($value = TRUE) {
    return $this->storage->enforceIsNew($value);
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::getExportProperties().
   */
  public function getExportProperties() {
    return $this->storage->getExportProperties();
  }

  /**
   * Implements \Drupal\Core\TypedData\TranslatableInterface::getTranslation().
   */
  public function getTranslation($langcode) {
    // @todo Revisit this once config entities are converted to NG.
    return $this;
  }

  /**
   * Implements \Drupal\Core\TypedData\TranslatableInterface::getTranslationLanguages().
   */
  public function getTranslationLanguages($include_default = TRUE) {
    return $this->storage->getTranslationLanguages($include_default);
  }

  /**
   * Implements \Drupal\Core\TypedData\TranslatableInterface::language)().
   */
  public function language() {
    return $this->storage->language();
  }

  /**
   * Implements \Drupal\Core\TypedData\AccessibleInterface::access().
   */
  public function access($operation = 'view', AccountInterface $account = NULL) {
    return $this->storage->access($operation, $account);
  }

  /**
   * Implements \Drupal\Core\TypedData\ComplexDataInterface::isEmpty)().
   */
  public function isEmpty() {
    return $this->storage->isEmpty();
  }

  /**
   * Implements \Drupal\Core\TypedData\ComplexDataInterface::getPropertyValues().
   */
  public function getPropertyValues() {
    return $this->storage->getPropertyValues();
  }

  /**
   * Implements \Drupal\Core\TypedData\ComplexDataInterface::getPropertyDefinitions().
   */
  public function getPropertyDefinitions() {
    return $this->storage->getPropertyDefinitions();
  }

  /**
   * Implements \Drupal\Core\TypedData\ComplexDataInterface::getPropertyDefinition().
   */
  public function getPropertyDefinition($name) {
    return $this->storage->getPropertyDefinition($name);
  }

  /**
   * Implements \Drupal\Core\TypedData\ComplexDataInterface::setPropertyValues().
   */
  public function setPropertyValues($values) {
    return $this->storage->setPropertyValues($values);
  }

  /**
   * Implements \Drupal\Core\TypedData\ComplexDataInterface::getProperties().
   */
  public function getProperties($include_computed = FALSE) {
    return $this->storage->getProperties($include_computed);
  }

  /**
   * Implements \Drupal\Core\Config\Entity\ConfigEntityInterface::enable().
   */
  public function enable() {
    return $this->storage->enable();
  }

  /**
   * Implements \Drupal\Core\Config\Entity\ConfigEntityInterface::disable().
   */
  public function disable() {
    return $this->storage->disable();
  }

  /**
   * Implements \Drupal\Core\Config\Entity\ConfigEntityInterface::status().
   */
  public function status() {
    return $this->storage->status();
  }

  /**
   * Implements \Drupal\Core\Config\Entity\ConfigEntityInterface::getOriginalID().
   */
  public function getOriginalID() {
    return $this->storage->getOriginalID();
  }

  /**
   * Implements \Drupal\Core\Config\Entity\ConfigEntityInterface::setOriginalID().
   */
  public function setOriginalID($id) {
    return $this->storage->setOriginalID($id);
  }

  /**
   * Implements Drupal\Core\Entity\EntityInterface::getBCEntity().
   */
  public function getBCEntity() {
    return $this->storage->getBCEntity();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::getNGEntity().
   */
  public function getNGEntity() {
    return $this->storage->getNGEntity();
  }

  /**
   * Implements \Drupal\Core\Entity\EntityInterface::isTranslatable().
   */
  public function isTranslatable() {
    return $this->storage->isTranslatable();
  }

  /**
   * {@inheritdoc}
   */
  public function getUntranslated() {
    return $this->storage->getUntranslated();
  }

  /**
   * {@inheritdoc}
   */
  public function hasTranslation($langcode) {
    return $this->storage->hasTranslation($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function addTranslation($langcode, array $values = array()) {
    return $this->storage->addTranslation($langcode, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function removeTranslation($langcode) {
    $this->storage->removeTranslation($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function initTranslation($langcode) {
    $this->storage->initTranslation($langcode);
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getType().
   */
  public function getType() {
    return $this->storage->getType();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getDefinition().
   */
  public function getDefinition() {
    return $this->storage->getDefinition();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getValue().
   */
  public function getValue() {
    return $this->storage->getValue();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::setValue().
   */
  public function setValue($value, $notify = TRUE) {
    return $this->storage->setValue($value, $notify);
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getString().
   */
  public function getString() {
    return $this->storage->getString();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getConstraints().
   */
  public function getConstraints() {
    return $this->storage->getConstraints();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::validate().
   */
  public function validate() {
    return $this->storage->validate();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getName().
   */
  public function getName() {
    return $this->storage->getName();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getRoot().
   */
  public function getRoot() {
    return $this->storage->getRoot();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getPropertyPath().
   */
  public function getPropertyPath() {
    return $this->storage->getPropertyPath();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getParent().
   */
  public function getParent() {
    return $this->storage->getParent();
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::setContext().
   */
  public function setContext($name = NULL, TypedDataInterface $parent = NULL) {
    return $this->storage->setContext($name, $parent);
  }

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::onChange().
   */
  public function onChange($property_name) {
    $this->storage->onChange($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    return $this->storage->applyDefaultValue($notify);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageControllerInterface $storage_controller) {
    $this->storage->presave($storage_controller);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageControllerInterface $storage_controller, $update = TRUE) {
    $this->storage->postSave($storage_controller, $update);
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageControllerInterface $storage_controller, array &$values) {
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageControllerInterface $storage_controller) {
    $this->storage->postCreate($storage_controller);
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageControllerInterface $storage_controller, array $entities) {
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageControllerInterface $storage_controller, array $entities) {
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageControllerInterface $storage_controller, array $entities) {
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageControllerInterface $storage_controller, \stdClass $record) {
    $this->storage->preSaveRevision($storage_controller, $record);
  }

  /**
   * {@inheritdoc}
   */
  public function mergeDefaultDisplaysOptions() {
    $this->storage->mergeDefaultDisplaysOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function uriRelationships() {
    return $this->storage->uriRelationships();
  }
}
