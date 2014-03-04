<?php

/**
 * @file
 * Definition of Drupal\node\NodeFormController.
 */

namespace Drupal\node;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Language\Language;

/**
 * Form controller for the node edit forms.
 */
class NodeFormController extends EntityFormController {

  /**
   * Default settings for this content/node type.
   *
   * @var array
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    $node = $this->entity;
    // Set up default values, if required.
    $type = entity_load('node_type', $node->bundle());
    $this->settings = $type->getModuleSettings('node');
    $this->settings += array(
      'options' => array('status', 'promote'),
      'preview' => DRUPAL_OPTIONAL,
      'submitted' => TRUE,
    );

    // If this is a new node, fill in the default values.
    if ($node->isNew()) {
      foreach (array('status', 'promote', 'sticky') as $key) {
        // Multistep node forms might have filled in something already.
        if (!isset($node->$key)) {
          $node->$key = (int) in_array($key, $this->settings['options']);
        }
      }
      global $user;
      $node->uid = $user->id();
      $node->created = REQUEST_TIME;
    }
    else {
      $node->date = format_date($node->created, 'custom', 'Y-m-d H:i:s O');
      // Remove the log message from the original node entity.
      $node->log = NULL;
    }
    // Always use the default revision setting.
    $node->setNewRevision(in_array('revision', $this->settings['options']));
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   */
  public function form(array $form, array &$form_state) {
    $node = $this->entity;

    if ($this->operation == 'edit') {
      drupal_set_title(t('<em>Edit @type</em> @title', array('@type' => node_get_type_label($node), '@title' => $node->label())), PASS_THROUGH);
    }

    $user_config = \Drupal::config('user.settings');
    // Some special stuff when previewing a node.
    if (isset($form_state['node_preview'])) {
      $form['#prefix'] = $form_state['node_preview'];
      $node->in_preview = TRUE;
    }
    else {
      unset($node->in_preview);
    }

    // Override the default CSS class name, since the user-defined node type
    // name in 'TYPE-node-form' potentially clashes with third-party class
    // names.
    $form['#attributes']['class'][0] = drupal_html_class('node-' . $node->type . '-form');

    // Basic node information.
    // These elements are just values so they are not even sent to the client.
    foreach (array('nid', 'vid', 'uid', 'created', 'type') as $key) {
      $form[$key] = array(
        '#type' => 'value',
        '#value' => isset($node->$key) ? $node->$key : NULL,
      );
    }

    // Changed must be sent to the client, for later overwrite error checking.
    $form['changed'] = array(
      '#type' => 'hidden',
      '#default_value' => isset($node->changed) ? $node->changed : NULL,
    );

    $node_type = node_type_load($node->type);
    if ($node_type->has_title) {
      $form['title'] = array(
        '#type' => 'textfield',
        '#title' => check_plain($node_type->title_label),
        '#required' => TRUE,
        '#default_value' => $node->title,
        '#maxlength' => 255,
        '#weight' => -5,
      );
    }

    $language_configuration = module_invoke('language', 'get_default_configuration', 'node', $node->type);
    $form['langcode'] = array(
      '#title' => t('Language'),
      '#type' => 'language_select',
      '#default_value' => $node->langcode,
      '#languages' => Language::STATE_ALL,
      '#access' => isset($language_configuration['language_show']) && $language_configuration['language_show'],
    );

    $form['advanced'] = array(
      '#type' => 'vertical_tabs',
      '#attributes' => array('class' => array('entity-meta')),
      '#weight' => 99,
    );

    // Add a log field if the "Create new revision" option is checked, or if
    // the current user has the ability to check that option.
    $form['revision_information'] = array(
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => t('Revision information'),
      // Collapsed by default when "Create new revision" is unchecked.
      '#collapsed' => !$node->isNewRevision(),
      '#attributes' => array(
        'class' => array('node-form-revision-information'),
      ),
      '#attached' => array(
        'js' => array(drupal_get_path('module', 'node') . '/node.js'),
      ),
      '#weight' => 20,
      '#access' => $node->isNewRevision() || user_access('administer nodes'),
    );

    $form['revision_information']['revision']['revision'] = array(
      '#type' => 'checkbox',
      '#title' => t('Create new revision'),
      '#default_value' => $node->isNewRevision(),
      '#access' => user_access('administer nodes'),
    );

    $form['revision_information']['revision']['log'] = array(
      '#type' => 'textarea',
      '#title' => t('Revision log message'),
      '#rows' => 4,
      '#default_value' => !empty($node->log) ? $node->log : '',
      '#description' => t('Briefly describe the changes you have made.'),
      '#states' => array(
        'visible' => array(
          ':input[name="revision"]' => array('checked' => TRUE),
        ),
      ),
    );

    // Node author information for administrators.
    $form['author'] = array(
      '#type' => 'details',
      '#access' => user_access('administer nodes'),
      '#title' => t('Authoring information'),
      '#collapsed' => TRUE,
      '#group' => 'advanced',
      '#attributes' => array(
        'class' => array('node-form-author'),
      ),
      '#attached' => array(
        'js' => array(
          drupal_get_path('module', 'node') . '/node.js',
          array(
            'type' => 'setting',
            'data' => array('anonymous' => $user_config->get('anonymous')),
          ),
        ),
      ),
      '#weight' => 90,
    );

    $form['author']['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Authored by'),
      '#maxlength' => 60,
      '#autocomplete_path' => 'user/autocomplete',
      '#default_value' => !empty($node->name) ? $node->name : '',
      '#weight' => -1,
      '#description' => t('Leave blank for %anonymous.', array('%anonymous' => $user_config->get('anonymous'))),
    );
    $form['author']['date'] = array(
      '#type' => 'textfield',
      '#title' => t('Authored on'),
      '#maxlength' => 25,
      '#description' => t('Format: %time. The date format is YYYY-MM-DD and %timezone is the time zone offset from UTC. Leave blank to use the time of form submission.', array('%time' => !empty($node->date) ? date_format(date_create($node->date), 'Y-m-d H:i:s O') : format_date($node->created, 'custom', 'Y-m-d H:i:s O'), '%timezone' => !empty($node->date) ? date_format(date_create($node->date), 'O') : format_date($node->created, 'custom', 'O'))),
      '#default_value' => !empty($node->date) ? $node->date : '',
    );

    // Node options for administrators.
    $form['options'] = array(
      '#type' => 'details',
      '#access' => user_access('administer nodes'),
      '#title' => t('Promotion options'),
      '#collapsed' => TRUE,
      '#group' => 'advanced',
      '#attributes' => array(
        'class' => array('node-form-options'),
      ),
      '#attached' => array(
        'js' => array(drupal_get_path('module', 'node') . '/node.js'),
      ),
      '#weight' => 95,
    );

    $form['options']['promote'] = array(
      '#type' => 'checkbox',
      '#title' => t('Promoted to front page'),
      '#default_value' => $node->promote,
    );

    $form['options']['sticky'] = array(
      '#type' => 'checkbox',
      '#title' => t('Sticky at top of lists'),
      '#default_value' => $node->sticky,
    );

    // This form uses a button-level #submit handler for the form's main submit
    // action. node_form_submit() manually invokes all form-level #submit
    // handlers of the form. Without explicitly setting #submit, Form API would
    // auto-detect node_form_submit() as submit handler, but that is the
    // button-level #submit handler for the 'Save' action.
    $form += array('#submit' => array());

    return parent::form($form, $form_state, $node);
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   */
  protected function actions(array $form, array &$form_state) {
    $element = parent::actions($form, $form_state);
    $node = $this->entity;
    $preview_mode = $this->settings['preview'];

    $element['submit']['#access'] = $preview_mode != DRUPAL_REQUIRED || (!form_get_errors() && isset($form_state['node_preview']));

    // If saving is an option, privileged users get dedicated form submit
    // buttons to adjust the publishing status while saving in one go.
    // @todo This adjustment makes it close to impossible for contributed
    //   modules to integrate with "the Save operation" of this form. Modules
    //   need a way to plug themselves into 1) the ::submit() step, and
    //   2) the ::save() step, both decoupled from the pressed form button.
    if ($element['submit']['#access'] && user_access('administer nodes')) {
      // isNew | prev status » default   & publish label             & unpublish label
      // 1     | 1           » publish   & Save and publish          & Save as unpublished
      // 1     | 0           » unpublish & Save and publish          & Save as unpublished
      // 0     | 1           » publish   & Save and keep published   & Save and unpublish
      // 0     | 0           » unpublish & Save and keep unpublished & Save and publish

      // Add a "Publish" button.
      $element['publish'] = $element['submit'];
      $element['publish']['#dropbutton'] = 'save';
      if ($node->isNew()) {
        $element['publish']['#value'] = t('Save and publish');
      }
      else {
        $element['publish']['#value'] = $node->status ? t('Save and keep published') : t('Save and publish');
      }
      $element['publish']['#weight'] = 0;
      array_unshift($element['publish']['#submit'], array($this, 'publish'));

      // Add a "Unpublish" button.
      $element['unpublish'] = $element['submit'];
      $element['unpublish']['#dropbutton'] = 'save';
      if ($node->isNew()) {
        $element['unpublish']['#value'] = t('Save as unpublished');
      }
      else {
        $element['unpublish']['#value'] = !$node->status ? t('Save and keep unpublished') : t('Save and unpublish');
      }
      $element['unpublish']['#weight'] = 10;
      array_unshift($element['unpublish']['#submit'], array($this, 'unpublish'));

      // If already published, the 'publish' button is primary.
      if ($node->status) {
        unset($element['unpublish']['#button_type']);
      }
      // Otherwise, the 'unpublish' button is primary and should come first.
      else {
        unset($element['publish']['#button_type']);
        $element['unpublish']['#weight'] = -10;
      }

      // Remove the "Save" button.
      $element['submit']['#access'] = FALSE;
    }

    $element['preview'] = array(
      '#access' => $preview_mode != DRUPAL_DISABLED && (node_access('create', $node) || node_access('update', $node)),
      '#value' => t('Preview'),
      '#weight' => 20,
      '#validate' => array(
        array($this, 'validate'),
      ),
      '#submit' => array(
        array($this, 'submit'),
        array($this, 'preview'),
      ),
    );

    $element['delete']['#access'] = node_access('delete', $node);
    $element['delete']['#weight'] = 100;

    return $element;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::validate().
   */
  public function validate(array $form, array &$form_state) {
    $node = $this->buildEntity($form, $form_state);

    if ($node->id() && (node_last_changed($node->id(), $this->getFormLangcode($form_state)) > $node->changed)) {
      form_set_error('changed', t('The content on this page has either been modified by another user, or you have already submitted modifications using this form. As a result, your changes cannot be saved.'));
    }

    // Validate the "authored by" field.
    if (!empty($node->name) && !($account = user_load_by_name($node->name))) {
      // The use of empty() is mandatory in the context of usernames
      // as the empty string denotes the anonymous user. In case we
      // are dealing with an anonymous user we set the user ID to 0.
      form_set_error('name', t('The username %name does not exist.', array('%name' => $node->name)));
    }

    // Validate the "authored on" field.
    // The date element contains the date object.
    $date = $node->date instanceof DrupalDateTime ? $node->date : new DrupalDateTime($node->date);
    if ($date->hasErrors()) {
      form_set_error('date', t('You have to specify a valid date.'));
    }

    // Invoke hook_node_validate() for validation needed by modules.
    // Can't use module_invoke_all(), because $form_state must
    // be receivable by reference.
    foreach (\Drupal::moduleHandler()->getImplementations('node_validate') as $module) {
      $function = $module . '_node_validate';
      $function($node, $form, $form_state);
    }

    parent::validate($form, $form_state);
  }

  /**
   * Updates the node object by processing the submitted values.
   *
   * This function can be called by a "Next" button of a wizard to update the
   * form state's entity with the current step's values before proceeding to the
   * next step.
   *
   * Overrides Drupal\Core\Entity\EntityFormController::submit().
   */
  public function submit(array $form, array &$form_state) {
    // Build the node object from the submitted values.
    $node = parent::submit($form, $form_state);

    // Save as a new revision if requested to do so.
    if (!empty($form_state['values']['revision'])) {
      $node->setNewRevision();
    }

    node_submit($node);
    foreach (\Drupal::moduleHandler()->getImplementations('node_submit') as $module) {
      $function = $module . '_node_submit';
      $function($node, $form, $form_state);
    }

    return $node;
  }

  /**
   * Form submission handler for the 'preview' action.
   *
   * @param $form
   *   An associative array containing the structure of the form.
   * @param $form_state
   *   A reference to a keyed array containing the current state of the form.
   */
  public function preview(array $form, array &$form_state) {
    // @todo Remove this: we should not have explicit includes in autoloaded
    //   classes.
    module_load_include('inc', 'node', 'node.pages');
    drupal_set_title(t('Preview'), PASS_THROUGH);
    $form_state['node_preview'] = node_preview($this->entity);
    $form_state['rebuild'] = TRUE;
  }

  /**
   * Form submission handler for the 'publish' action.
   *
   * @param $form
   *   An associative array containing the structure of the form.
   * @param $form_state
   *   A reference to a keyed array containing the current state of the form.
   */
  public function publish(array $form, array &$form_state) {
    $node = $this->entity;
    $node->status = 1;
    return $node;
  }

  /**
   * Form submission handler for the 'unpublish' action.
   *
   * @param $form
   *   An associative array containing the structure of the form.
   * @param $form_state
   *   A reference to a keyed array containing the current state of the form.
   */
  public function unpublish(array $form, array &$form_state) {
    $node = $this->entity;
    $node->status = 0;
    return $node;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::save().
   */
  public function save(array $form, array &$form_state) {
    $node = $this->entity;
    $insert = $node->isNew();
    $node->save();
    $node_link = l(t('view'), 'node/' . $node->id());
    $watchdog_args = array('@type' => $node->type, '%title' => $node->label());
    $t_args = array('@type' => node_get_type_label($node), '%title' => $node->label());

    if ($insert) {
      watchdog('content', '@type: added %title.', $watchdog_args, WATCHDOG_NOTICE, $node_link);
      drupal_set_message(t('@type %title has been created.', $t_args));
    }
    else {
      watchdog('content', '@type: updated %title.', $watchdog_args, WATCHDOG_NOTICE, $node_link);
      drupal_set_message(t('@type %title has been updated.', $t_args));
    }

    if ($node->id()) {
      $form_state['values']['nid'] = $node->id();
      $form_state['nid'] = $node->id();
      $form_state['redirect'] = node_access('view', $node) ? 'node/' . $node->id() : '<front>';
    }
    else {
      // In the unlikely case something went wrong on save, the node will be
      // rebuilt and node form redisplayed the same way as in preview.
      drupal_set_message(t('The post could not be saved.'), 'error');
      $form_state['rebuild'] = TRUE;
    }

    // Clear the page and block caches.
    cache_invalidate_tags(array('content' => TRUE));
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::delete().
   */
  public function delete(array $form, array &$form_state) {
    $destination = array();
    $query = \Drupal::request()->query;
    if ($query->has('destination')) {
      $destination = drupal_get_destination();
      $query->remove('destination');
    }
    $node = $this->entity;
    $form_state['redirect'] = array('node/' . $node->id() . '/delete', array('query' => $destination));
  }

}
