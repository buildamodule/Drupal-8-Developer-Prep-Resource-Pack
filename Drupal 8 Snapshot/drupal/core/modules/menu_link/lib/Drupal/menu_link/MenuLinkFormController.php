<?php

/**
 * @file
 * Contains \Drupal\menu_link\MenuLinkFormController.
 */

namespace Drupal\menu_link;

use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Language\Language;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Routing\UrlGenerator;
use Drupal\menu_link\MenuLinkStorageControllerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the node edit forms.
 */
class MenuLinkFormController extends EntityFormController implements EntityControllerInterface {

  /**
   * The menu link storage controller.
   *
   * @var \Drupal\menu_link\MenuLinkStorageControllerInterface
   */
  protected $menuLinkStorageController;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGenerator
   */
  protected $urlGenerator;

  /**
   * Constructs a new MenuLinkFormController object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler service.
   * @param \Drupal\Core\Path\AliasManagerInterface $path_alias_manager
   *   The path alias manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, MenuLinkStorageControllerInterface $menu_link_storage_controller, AliasManagerInterface $path_alias_manager, UrlGenerator $url_generator) {
    parent::__construct($module_handler);
    $this->menuLinkStorageController = $menu_link_storage_controller;
    $this->pathAliasManager = $path_alias_manager;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info, $operation = NULL) {
    return new static(
      $container->get('module_handler'),
      $container->get('plugin.manager.entity')->getStorageController('menu_link'),
      $container->get('path.alias_manager.cached'),
      $container->get('url_generator')
    );
  }

  /**
   * Overrides EntityFormController::form().
   */
  public function form(array $form, array &$form_state) {
    $menu_link = $this->entity;
    // Since menu_link_load() no longer returns a translated and access checked
    // item, do it here instead.
    _menu_link_translate($menu_link);

    if (!$menu_link->isNew()) {
      // Get the human-readable menu title from the given menu name.
      $titles = menu_get_menus();
      $current_title = $titles[$menu_link->menu_name];

      // Get the current breadcrumb and add a link to that menu's overview page.
      $breadcrumb = menu_get_active_breadcrumb();
      $breadcrumb[] = l($current_title, 'admin/structure/menu/manage/' . $menu_link->menu_name);
      drupal_set_breadcrumb($breadcrumb);
    }

    $form['link_title'] = array(
      '#type' => 'textfield',
      '#title' => t('Menu link title'),
      '#default_value' => $menu_link->link_title,
      '#description' => t('The text to be used for this link in the menu.'),
      '#required' => TRUE,
    );
    foreach (array('link_path', 'mlid', 'module', 'has_children', 'options') as $key) {
      $form[$key] = array('#type' => 'value', '#value' => $menu_link->{$key});
    }
    // Any item created or edited via this interface is considered "customized".
    $form['customized'] = array('#type' => 'value', '#value' => 1);

    // We are not using url() when constructing this path because it would add
    // $base_path.
    $path = $menu_link->link_path;
    if (isset($menu_link->options['query'])) {
      $path .= '?' . $this->urlGenerator->httpBuildQuery($menu_link->options['query']);
    }
    if (isset($menu_link->options['fragment'])) {
      $path .= '#' . $menu_link->options['fragment'];
    }
    if ($menu_link->module == 'menu') {
      $form['link_path'] = array(
        '#type' => 'textfield',
        '#title' => t('Path'),
        '#maxlength' => 255,
        '#default_value' => $path,
        '#description' => t('The path for this menu link. This can be an internal Drupal path such as %add-node or an external URL such as %drupal. Enter %front to link to the front page.', array('%front' => '<front>', '%add-node' => 'node/add', '%drupal' => 'http://drupal.org')),
        '#required' => TRUE,
      );
    }
    else {
      $form['_path'] = array(
        '#type' => 'item',
        '#title' => t('Path'),
        '#description' => l($menu_link->link_title, $menu_link->href, $menu_link->options),
      );
    }

    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => isset($menu_link->options['attributes']['title']) ? $menu_link->options['attributes']['title'] : '',
      '#rows' => 1,
      '#description' => t('Shown when hovering over the menu link.'),
    );
    $form['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#default_value' => !$menu_link->hidden,
      '#description' => t('Menu links that are not enabled will not be listed in any menu.'),
    );
    $form['expanded'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show as expanded'),
      '#default_value' => $menu_link->expanded,
      '#description' => t('If selected and this menu link has children, the menu will always appear expanded.'),
    );

    // Generate a list of possible parents (not including this link or descendants).
    $options = menu_parent_options(menu_get_menus(), $menu_link);
    $default = $menu_link->menu_name . ':' . $menu_link->plid;
    if (!isset($options[$default])) {
      $default = 'tools:0';
    }
    $form['parent'] = array(
      '#type' => 'select',
      '#title' => t('Parent link'),
      '#default_value' => $default,
      '#options' => $options,
      '#description' => t('The maximum depth for a link and all its children is fixed at !maxdepth. Some menu links may not be available as parents if selecting them would exceed this limit.', array('!maxdepth' => MENU_MAX_DEPTH)),
      '#attributes' => array('class' => array('menu-title-select')),
    );

    // Get number of items in menu so the weight selector is sized appropriately.
    $delta = $this->menuLinkStorageController->countMenuLinks($menu_link->menu_name);
    $form['weight'] = array(
      '#type' => 'weight',
      '#title' => t('Weight'),
      // Old hardcoded value.
      '#delta' => max($delta, 50),
      '#default_value' => $menu_link->weight,
      '#description' => t('Optional. In the menu, the heavier links will sink and the lighter links will be positioned nearer the top.'),
    );

    // Language module allows to configure the menu link language independently
    // of the menu language. It also allows to optionally show the language
    // selector on the menu link form so that the language of each menu link can
    // be configured individually.
    if ($this->moduleHandler->moduleExists('language')) {
      $language_configuration = language_get_default_configuration('menu_link', $menu_link->bundle());
      $default_langcode = ($menu_link->isNew() ? $language_configuration['langcode'] : $menu_link->langcode);
      $language_show = $language_configuration['language_show'];
    }
    // Without Language module menu links inherit the menu language and no
    // language selector is shown.
    else {
      $default_langcode = ($menu_link->isNew() ? entity_load('menu', $menu_link->menu_name)->langcode : $menu_link->langcode);
      $language_show = FALSE;
    }

    $form['langcode'] = array(
      '#type' => 'language_select',
      '#title' => t('Language'),
      '#languages' => Language::STATE_ALL,
      '#default_value' => $default_langcode,
      '#access' => $language_show,
    );

    return parent::form($form, $form_state, $menu_link);
  }

  /**
   * Overrides EntityFormController::actions().
   */
  protected function actions(array $form, array &$form_state) {
    $element = parent::actions($form, $form_state);
    $element['submit']['#button_type'] = 'primary';
    $element['delete']['#access'] = $this->entity->access('delete');

    return $element;
  }

  /**
   * Overrides EntityFormController::validate().
   */
  public function validate(array $form, array &$form_state) {
    $menu_link = $this->buildEntity($form, $form_state);

    $normal_path = $this->pathAliasManager->getSystemPath($menu_link->link_path);
    if ($menu_link->link_path != $normal_path) {
      drupal_set_message(t('The menu system stores system paths only, but will use the URL alias for display. %link_path has been stored as %normal_path', array('%link_path' => $menu_link->link_path, '%normal_path' => $normal_path)));
      $menu_link->link_path = $normal_path;
    }
    if (!url_is_external($menu_link->link_path)) {
      $parsed_link = parse_url($menu_link->link_path);
      if (isset($parsed_link['query'])) {
        $menu_link->options['query'] = array();
        parse_str($parsed_link['query'], $menu_link->options['query']);
      }
      else {
        // Use unset() rather than setting to empty string
        // to avoid redundant serialized data being stored.
        unset($menu_link->options['query']);
      }
      if (isset($parsed_link['fragment'])) {
        $menu_link->options['fragment'] = $parsed_link['fragment'];
      }
      else {
        unset($menu_link->options['fragment']);
      }
      if (isset($parsed_link['path']) && $menu_link->link_path != $parsed_link['path']) {
        $menu_link->link_path = $parsed_link['path'];
      }
    }
    if (!trim($menu_link->link_path) || !drupal_valid_path($menu_link->link_path, TRUE)) {
      form_set_error('link_path', t("The path '@link_path' is either invalid or you do not have access to it.", array('@link_path' => $menu_link->link_path)));
    }

    parent::validate($form, $form_state);
  }

  /**
   * Overrides EntityFormController::submit().
   */
  public function submit(array $form, array &$form_state) {
    // Build the menu link object from the submitted values.
    $menu_link = parent::submit($form, $form_state);

    // The value of "hidden" is the opposite of the value supplied by the
    // "enabled" checkbox.
    $menu_link->hidden = (int) !$menu_link->enabled;
    unset($menu_link->enabled);

    $menu_link->options['attributes']['title'] = $menu_link->description;
    list($menu_link->menu_name, $menu_link->plid) = explode(':', $menu_link->parent);

    return $menu_link;
  }

  /**
   * Overrides EntityFormController::save().
   */
  public function save(array $form, array &$form_state) {
    $menu_link = $this->entity;

    $saved = $menu_link->save();

    if ($saved) {
      drupal_set_message(t('The menu link has been saved.'));
      $form_state['redirect'] = 'admin/structure/menu/manage/' . $menu_link->menu_name;
    }
    else {
      drupal_set_message(t('There was an error saving the menu link.'), 'error');
      $form_state['rebuild'] = TRUE;
    }
  }

  /**
   * Overrides EntityFormController::delete().
   */
  public function delete(array $form, array &$form_state) {
    $menu_link = $this->entity;
    $form_state['redirect'] = 'admin/structure/menu/item/' . $menu_link->id() . '/delete';
  }
}
