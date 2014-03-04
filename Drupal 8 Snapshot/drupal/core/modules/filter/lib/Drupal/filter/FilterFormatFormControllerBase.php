<?php

/**
 * @file
 * Contains \Drupal\filter\FilterFormatFormControllerBase.
 */

namespace Drupal\filter;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\filter\Plugin\Filter\FilterNull;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base form controller for a filter format.
 */
abstract class FilterFormatFormControllerBase extends EntityFormController implements EntityControllerInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The entity query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * Constructs a new FilterFormatFormControllerBase.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query factory.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ConfigFactory $config_factory, QueryFactory $query_factory) {
    parent::__construct($module_handler);
    $this->configFactory = $config_factory;
    $this->queryFactory = $query_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    $format = $this->entity;
    $is_fallback = ($format->id() == $this->configFactory->get('filter.settings')->get('fallback_format'));

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = array('filter', 'drupal.filter.admin');

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#default_value' => $format->label(),
      '#required' => TRUE,
      '#weight' => -30,
    );
    $form['format'] = array(
      '#type' => 'machine_name',
      '#required' => TRUE,
      '#default_value' => $format->id(),
      '#maxlength' => 255,
      '#machine_name' => array(
        'exists' => 'filter_format_exists',
        'source' => array('name'),
      ),
      '#disabled' => !$format->isNew(),
      '#weight' => -20,
    );

    // Add user role access selection.
    $form['roles'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Roles'),
      '#options' => array_map('\Drupal\Component\Utility\String::checkPlain', user_role_names()),
      '#disabled' => $is_fallback,
      '#weight' => -10,
    );
    if ($is_fallback) {
      $form['roles']['#description'] = t('All roles for this text format must be enabled and cannot be changed.');
    }
    if (!$format->isNew()) {
      // If editing an existing text format, pre-select its current permissions.
      $form['roles']['#default_value'] = array_keys(filter_get_roles_by_format($format));
    }
    elseif ($admin_role = $this->configFactory->get('user.settings')->get('admin_role')) {
      // If adding a new text format and the site has an administrative role,
      // pre-select that role so as to grant administrators access to the new
      // text format permission by default.
      $form['roles']['#default_value'] = array($admin_role);
    }

    // Create filter plugin instances for all available filters, including both
    // enabled/configured ones as well as new and not yet unconfigured ones.
    $filters = $format->filters()->sort();
    foreach ($filters as $filter_id => $filter) {
      // When a filter is missing, it is replaced by the null filter. Remove it
      // here, so that saving the form will remove the missing filter.
      if ($filter instanceof FilterNull) {
        drupal_set_message(t('The %filter filter is missing, and will be removed once this format is saved.', array('%filter' => $filter_id)), 'warning');
        $filters->removeInstanceID($filter_id);
      }
    }

    // Filter status.
    $form['filters']['status'] = array(
      '#type' => 'item',
      '#title' => t('Enabled filters'),
      '#prefix' => '<div id="filters-status-wrapper">',
      '#suffix' => '</div>',
      // This item is used as a pure wrapping container with heading. Ignore its
      // value, since 'filters' should only contain filter definitions.
      // @see http://drupal.org/node/1829202
      '#input' => FALSE,
    );
    // Filter order (tabledrag).
    $form['filters']['order'] = array(
      '#type' => 'table',
      // For filter.admin.js
      '#attributes' => array('id' => 'filter-order'),
      '#title' => t('Filter processing order'),
      '#tabledrag' => array(
        array('order', 'sibling', 'filter-order-weight'),
      ),
      '#tree' => FALSE,
      '#input' => FALSE,
      '#theme_wrappers' => array('form_element'),
    );
    // Filter settings.
    $form['filter_settings'] = array(
      '#type' => 'vertical_tabs',
      '#title' => t('Filter settings'),
    );

    foreach ($filters as $name => $filter) {
      $form['filters']['status'][$name] = array(
        '#type' => 'checkbox',
        '#title' => $filter->getLabel(),
        '#default_value' => $filter->status,
        '#parents' => array('filters', $name, 'status'),
        '#description' => $filter->getDescription(),
        '#weight' => $filter->weight,
      );

      $form['filters']['order'][$name]['#attributes']['class'][] = 'draggable';
      $form['filters']['order'][$name]['#weight'] = $filter->weight;
      $form['filters']['order'][$name]['filter'] = array(
        '#markup' => $filter->getLabel(),
      );
      $form['filters']['order'][$name]['weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight for @title', array('@title' => $filter->getLabel())),
        '#title_display' => 'invisible',
        '#delta' => 50,
        '#default_value' => $filter->weight,
        '#parents' => array('filters', $name, 'weight'),
        '#attributes' => array('class' => array('filter-order-weight')),
      );

      // Retrieve the settings form of the filter plugin. The plugin should not be
      // aware of the text format. Therefore, it only receives a set of minimal
      // base properties to allow advanced implementations to work.
      $settings_form = array(
        '#parents' => array('filters', $name, 'settings'),
        '#tree' => TRUE,
      );
      $settings_form = $filter->settingsForm($settings_form, $form_state);
      if (!empty($settings_form)) {
        $form['filters']['settings'][$name] = array(
          '#type' => 'details',
          '#title' => $filter->getLabel(),
          '#weight' => $filter->weight,
          '#parents' => array('filters', $name, 'settings'),
          '#group' => 'filter_settings',
        );
        $form['filters']['settings'][$name] += $settings_form;
      }
    }
    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    parent::validate($form, $form_state);

    // @todo Move trimming upstream.
    $format_format = trim($form_state['values']['format']);
    $format_name = trim($form_state['values']['name']);

    // Ensure that the values to be saved later are exactly the ones validated.
    form_set_value($form['format'], $format_format, $form_state);
    form_set_value($form['name'], $format_name, $form_state);

    $format_exists = $this->queryFactory
      ->get('filter_format')
      ->condition('format', $format_format, '<>')
      ->condition('name', $format_name)
      ->execute();
    if ($format_exists) {
      form_set_error('name', t('Text format names must be unique. A format named %name already exists.', array('%name' => $format_name)));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    parent::submit($form, $form_state);

    // Add the submitted form values to the text format, and save it.
    $format = $this->entity;
    foreach ($form_state['values'] as $key => $value) {
      if ($key != 'filters') {
        $format->set($key, $value);
      }
      else {
        foreach ($value as $instance_id => $config) {
          $format->setFilterConfig($instance_id, $config);
        }
      }
    }
    $format->save();

    // Save user permissions.
    if ($permission = filter_permission_name($format)) {
      foreach ($form_state['values']['roles'] as $rid => $enabled) {
        user_role_change_permissions($rid, array($permission => $enabled));
      }
    }

    $form_state['redirect'] = 'admin/config/content/formats';

    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, array &$form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = t('Save configuration');
    unset($actions['delete']);
    return $actions;
  }

}
