<?php

/**
 * @file
 * Contains Drupal\views_ui\ViewAddFormController.
 */

namespace Drupal\views_ui;

use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\views\Plugin\views\wizard\WizardPluginBase;
use Drupal\views\Plugin\views\wizard\WizardException;
use Drupal\views\Plugin\ViewsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the Views edit form.
 */
class ViewAddFormController extends ViewFormControllerBase implements EntityControllerInterface {

  /**
   * The wizard plugin manager.
   *
   * @var \Drupal\views\Plugin\ViewsPluginManager
   */
  protected $wizardManager;

  /**
   * Constructs a new ViewEditFormController object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler service.
   * @param \Drupal\views\Plugin\ViewsPluginManager $wizard_manager
   *   The wizard plugin manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ViewsPluginManager $wizard_manager) {
    parent::__construct($module_handler);

    $this->wizardManager = $wizard_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $container->get('module_handler'),
      $container->get('plugin.manager.views.wizard')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(array &$form_state) {
    parent::init($form_state);

    drupal_set_title(t('Add new view'));
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::prepareForm().
   */
  protected function prepareEntity() {
    // Do not prepare the entity while it is being added.
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   */
  public function form(array $form, array &$form_state) {
    $form['#attached']['css'] = static::getAdminCSS();
    $form['#attached']['js'][] = drupal_get_path('module', 'views_ui') . '/js/views-admin.js';
    $form['#attributes']['class'] = array('views-admin');

    $form['name'] = array(
      '#type' => 'fieldset',
      '#attributes' => array('class' => array('fieldset-no-legend')),
    );

    $form['name']['label'] = array(
      '#type' => 'textfield',
      '#title' => t('View name'),
      '#required' => TRUE,
      '#size' => 32,
      '#default_value' => '',
      '#maxlength' => 255,
    );
    $form['name']['id'] = array(
      '#type' => 'machine_name',
      '#maxlength' => 128,
      '#machine_name' => array(
        'exists' => 'views_get_view',
        'source' => array('name', 'label'),
      ),
      '#description' => t('A unique machine-readable name for this View. It must only contain lowercase letters, numbers, and underscores.'),
    );

    $form['name']['description_enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Description'),
    );
    $form['name']['description'] = array(
      '#type' => 'textfield',
      '#title' => t('Provide description'),
      '#title_display' => 'invisible',
      '#size' => 64,
      '#default_value' => '',
      '#states' => array(
        'visible' => array(
          ':input[name="description_enable"]' => array('checked' => TRUE),
        ),
      ),
    );

    // Create a wrapper for the entire dynamic portion of the form. Everything
    // that can be updated by AJAX goes somewhere inside here. For example, this
    // is needed by "Show" dropdown (below); it changes the base table of the
    // view and therefore potentially requires all options on the form to be
    // dynamically updated.
    $form['displays'] = array();

    // Create the part of the form that allows the user to select the basic
    // properties of what the view will display.
    $form['displays']['show'] = array(
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#attributes' => array('class' => array('container-inline')),
    );

    // Create the "Show" dropdown, which allows the base table of the view to be
    // selected.
    $wizard_plugins = $this->wizardManager->getDefinitions();
    $options = array();
    foreach ($wizard_plugins as $key => $wizard) {
      $options[$key] = $wizard['title'];
    }
    $form['displays']['show']['wizard_key'] = array(
      '#type' => 'select',
      '#title' => t('Show'),
      '#options' => $options,
    );
    $show_form = &$form['displays']['show'];
    $default_value = \Drupal::moduleHandler()->moduleExists('node') ? 'node' : 'users';
    $show_form['wizard_key']['#default_value'] = WizardPluginBase::getSelected($form_state, array('show', 'wizard_key'), $default_value, $show_form['wizard_key']);
    // Changing this dropdown updates the entire content of $form['displays'] via
    // AJAX.
    views_ui_add_ajax_trigger($show_form, 'wizard_key', array('displays'));

    // Build the rest of the form based on the currently selected wizard plugin.
    $wizard_key = $show_form['wizard_key']['#default_value'];
    $wizard_instance = $this->wizardManager->createInstance($wizard_key);
    $form = $wizard_instance->buildForm($form, $form_state);

    return $form;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   */
  protected function actions(array $form, array &$form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = t('Save and edit');

    $actions['cancel'] = array(
      '#value' => t('Cancel'),
      '#submit' => array(
        array($this, 'cancel'),
      ),
      '#limit_validation_errors' => array(),
    );
    return $actions;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::validate().
   */
  public function validate(array $form, array &$form_state) {
    $wizard_type = $form_state['values']['show']['wizard_key'];
    $wizard_instance = $this->wizardManager->createInstance($wizard_type);
    $form_state['wizard'] = $wizard_instance->getPluginDefinition();
    $form_state['wizard_instance'] = $wizard_instance;
    $errors = $form_state['wizard_instance']->validateView($form, $form_state);

    foreach ($errors as $display_errors) {
      foreach ($display_errors as $name => $message) {
        form_set_error($name, $message);
      }
    }
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::submit().
   */
  public function submit(array $form, array &$form_state) {
    try {
      $view = $form_state['wizard_instance']->createView($form, $form_state);
    }
    // @todo Figure out whether it really makes sense to throw and catch exceptions on the wizard.
    catch (WizardException $e) {
      drupal_set_message($e->getMessage(), 'error');
      $form_state['redirect'] = 'admin/structure/views';
      return;
    }
    $view->save();

    $form_state['redirect'] = array('admin/structure/views/view/' . $view->id());
  }

  /**
   * Form submission handler for the 'cancel' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   A reference to a keyed array containing the current state of the form.
   */
  public function cancel(array $form, array &$form_state) {
    $form_state['redirect'] = 'admin/structure/views';
  }

}
