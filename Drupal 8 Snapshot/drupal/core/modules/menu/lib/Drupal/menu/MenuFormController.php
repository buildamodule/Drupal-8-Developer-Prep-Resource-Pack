<?php

/**
 * @file
 * Contains \Drupal\menu\MenuFormController.
 */

namespace Drupal\menu;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\menu_link\MenuLinkStorageControllerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form controller for menu edit forms.
 */
class MenuFormController extends EntityFormController implements EntityControllerInterface {

  /**
   * The factory for entity queries.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryFactory;

  /**
   * The menu link storage controller.
   *
   * @var \Drupal\menu_link\MenuLinkStorageControllerInterface
   */
  protected $menuLinkStorage;

  /**
   * The overview tree form.
   *
   * @var array
   */
  protected $overviewTreeForm = array('#tree' => TRUE);

  /**
   * Constructs a MenuFormController object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke hooks on.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query_factory
   *   The factory for entity queries.
   * @param \Drupal\menu_link\MenuLinkStorageControllerInterface $menu_link_storage
   *   The menu link storage controller.
   */
  public function __construct(ModuleHandlerInterface $module_handler, QueryFactory $entity_query_factory, MenuLinkStorageControllerInterface $menu_link_storage) {
    parent::__construct($module_handler);

    $this->entityQueryFactory = $entity_query_factory;
    $this->menuLinkStorage = $menu_link_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $container->get('module_handler'),
      $container->get('entity.query'),
      $container->get('plugin.manager.entity')->getStorageController('menu_link')
    );
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   */
  public function form(array $form, array &$form_state) {
    $menu = $this->entity;

    if ($this->operation == 'edit') {
      drupal_set_title(t('Edit menu %label', array('%label' => $menu->label())), PASS_THROUGH);
    }

    $system_menus = menu_list_system_menus();

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => $menu->label(),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#title' => t('Menu name'),
      '#default_value' => $menu->id(),
      '#maxlength' => MENU_MAX_MENU_NAME_LENGTH_UI,
      '#description' => t('A unique name to construct the URL for the menu. It must only contain lowercase letters, numbers and hyphens.'),
      '#field_prefix' => $menu->isNew() ? 'menu-' : '',
      '#machine_name' => array(
        'exists' => array($this, 'menuNameExists'),
        'source' => array('label'),
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
      ),
      // A menu's machine name cannot be changed.
      '#disabled' => !$menu->isNew() || isset($system_menus[$menu->id()]),
    );
    $form['description'] = array(
      '#type' => 'textfield',
      '#title' => t('Administrative summary'),
      '#maxlength' => 512,
      '#default_value' => $menu->description,
    );

    $form['langcode'] = array(
      '#type' => 'language_select',
      '#title' => t('Menu language'),
      '#languages' => Language::STATE_ALL,
      '#default_value' => $menu->langcode,
    );
    // Unlike the menu langcode, the default language configuration for menu
    // links only works with language module installed.
    if ($this->moduleHandler->moduleExists('language')) {
      $form['default_menu_links_language'] = array(
        '#type' => 'details',
        '#title' => t('Menu links language'),
      );
      $form['default_menu_links_language']['default_language'] = array(
        '#type' => 'language_configuration',
        '#entity_information' => array(
          'entity_type' => 'menu_link',
          'bundle' => $menu->id(),
        ),
        '#default_value' => language_get_default_configuration('menu_link', $menu->id()),
      );
    }

    // Add menu links administration form for existing menus.
    if (!$menu->isNew() || isset($system_menus[$menu->id()])) {
      // Form API supports constructing and validating self-contained sections
      // within forms, but does not allow to handle the form section's submission
      // equally separated yet. Therefore, we use a $form_state key to point to
      // the parents of the form section.
      // @see self::submitOverviewForm()
      $form_state['menu_overview_form_parents'] = array('links');
      $form['links'] = array();
      $form['links'] = $this->buildOverviewForm($form['links'], $form_state);
    }

    return parent::form($form, $form_state);
  }

  /**
   * Returns whether a menu name already exists.
   *
   * @param string $value
   *   The name of the menu.
   *
   * @return bool
   *   Returns TRUE if the menu already exists, FALSE otherwise.
   */
  public function menuNameExists($value) {
    // Check first to see if a menu with this ID exists.
    if ($this->entityQueryFactory->get('menu')->condition('id', $value)->range(0, 1)->count()->execute()) {
      return TRUE;
    }

    // Check for a link assigned to this menu. 'menu-' is added to the menu name
    // to avoid name-space conflicts.
    return $this->entityQueryFactory->get('menu_link')->condition('menu_name', 'menu-' . $value)->range(0, 1)->count()->execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, array &$form_state) {
    $actions = parent::actions($form, $form_state);

    $actions['delete']['#access'] = !$this->entity->isNew() && $this->entity->access('delete');

    // Add the language configuration submit handler. This is needed because the
    // submit button has custom submit handlers.
    if ($this->moduleHandler->moduleExists('language')) {
      array_unshift($actions['submit']['#submit'],'language_configuration_element_submit');
      array_unshift($actions['submit']['#submit'], array($this, 'languageConfigurationSubmit'));
    }
    // We cannot leverage the regular submit handler definition because we have
    // button-specific ones here. Hence we need to explicitly set it for the
    // submit action, otherwise it would be ignored.
    if ($this->moduleHandler->moduleExists('content_translation')) {
      array_unshift($actions['submit']['#submit'], 'content_translation_language_configuration_element_submit');
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    if ($this->entity->isNew()) {
      // The machine name is validated automatically, we only need to add the
      // 'menu-' prefix here.
      $form_state['values']['id'] = 'menu-' . $form_state['values']['id'];
    }
  }

  /**
   * Submit handler to update the bundle for the default language configuration.
   */
  public function languageConfigurationSubmit(array &$form, array &$form_state) {
    // Since the machine name is not known yet, and it can be changed anytime,
    // we have to also update the bundle property for the default language
    // configuration in order to have the correct bundle value.
    $form_state['language']['default_language']['bundle'] = $form_state['values']['id'];
    // Clear cache so new menus (bundles) show on the language settings admin
    // page.
    entity_info_cache_clear();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, array &$form_state) {
    $menu = $this->entity;
    // @todo Get rid of menu_list_system_menus() https://drupal.org/node/1882552
    //   Supposed menu item declared by hook_menu()
    //   Should be moved to submitOverviewForm()
    $system_menus = menu_list_system_menus();
    if (!$menu->isNew() || isset($system_menus[$menu->id()])) {
      $this->submitOverviewForm($form, $form_state);
    }

    $status = $menu->save();

    $uri = $menu->uri();
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Menu %label has been updated.', array('%label' => $menu->label())));
      watchdog('menu', 'Menu %label has been updated.', array('%label' => $menu->label()), WATCHDOG_NOTICE, l(t('Edit'), $uri['path'] . '/edit'));
    }
    else {
      drupal_set_message(t('Menu %label has been added.', array('%label' => $menu->label())));
      watchdog('menu', 'Menu %label has been added.', array('%label' => $menu->label()), WATCHDOG_NOTICE, l(t('Edit'), $uri['path'] . '/edit'));
    }

    $form_state['redirect'] = 'admin/structure/menu/manage/' . $menu->id();
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, array &$form_state) {
    $form_state['redirect'] = 'admin/structure/menu/manage/' . $this->entity->id() . '/delete';
  }

  /**
   * Form constructor to edit an entire menu tree at once.
   *
   * Shows for one menu the menu links accessible to the current user and
   * relevant operations.
   *
   * This form constructor can be integrated as a section into another form. It
   * relies on the following keys in $form_state:
   * - menu: A loaded menu definition, as returned by menu_load().
   * - menu_overview_form_parents: An array containing the parent keys to this
   *   form.
   * Forms integrating this section should call menu_overview_form_submit() from
   * their form submit handler.
   */
  protected function buildOverviewForm(array &$form, array &$form_state) {
    global $menu_admin;

    // Ensure that menu_overview_form_submit() knows the parents of this form
    // section.
    $form['#tree'] = TRUE;
    $form['#theme'] = 'menu_overview_form';
    $form_state += array('menu_overview_form_parents' => array());

    $form['#attached']['css'] = array(drupal_get_path('module', 'menu') . '/css/menu.admin.css');

    $links = array();
    $query = $this->entityQueryFactory->get('menu_link')
      ->condition('menu_name', $this->entity->id());
    for ($i = 1; $i <= MENU_MAX_DEPTH; $i++) {
      $query->sort('p' . $i, 'ASC');
    }
    $result = $query->execute();

    if (!empty($result)) {
      $links = $this->menuLinkStorage->loadMultiple($result);
    }

    $delta = max(count($links), 50);
    $tree = menu_tree_data($links);
    $node_links = array();
    menu_tree_collect_node_links($tree, $node_links);
    // We indicate that a menu administrator is running the menu access check.
    $menu_admin = TRUE;
    menu_tree_check_access($tree, $node_links);
    $menu_admin = FALSE;

    $form = array_merge($form, $this->buildOverviewTreeForm($tree, $delta));
    $form['#empty_text'] = t('There are no menu links yet. <a href="@link">Add link</a>.', array('@link' => url('admin/structure/menu/manage/' . $this->entity->id() .'/add')));

    return $form;
  }

  /**
   * Recursive helper function for buildOverviewForm().
   *
   * @param $tree
   *   The menu_tree retrieved by menu_tree_data.
   * @param $delta
   *   The default number of menu items used in the menu weight selector is 50.
   *
   * @return array
   *   The overview tree form.
   */
  protected function buildOverviewTreeForm($tree, $delta) {
    $form = &$this->overviewTreeForm;
    foreach ($tree as $data) {
      $item = $data['link'];
      // Don't show callbacks; these have $item['hidden'] < 0.
      if ($item && $item['hidden'] >= 0) {
        $mlid = 'mlid:' . $item['mlid'];
        $form[$mlid]['#item'] = $item;
        $form[$mlid]['#attributes'] = $item['hidden'] ? array('class' => array('menu-disabled')) : array('class' => array('menu-enabled'));
        $form[$mlid]['title']['#markup'] = l($item['title'], $item['href'], $item['localized_options']);
        if ($item['hidden']) {
          $form[$mlid]['title']['#markup'] .= ' (' . t('disabled') . ')';
        }
        elseif ($item['link_path'] == 'user' && $item['module'] == 'system') {
          $form[$mlid]['title']['#markup'] .= ' (' . t('logged in users only') . ')';
        }

        $form[$mlid]['hidden'] = array(
          '#type' => 'checkbox',
          '#title' => t('Enable @title menu link', array('@title' => $item['title'])),
          '#title_display' => 'invisible',
          '#default_value' => !$item['hidden'],
        );
        $form[$mlid]['weight'] = array(
          '#type' => 'weight',
          '#delta' => $delta,
          '#default_value' => $item['weight'],
          '#title_display' => 'invisible',
          '#title' => t('Weight for @title', array('@title' => $item['title'])),
        );
        $form[$mlid]['mlid'] = array(
          '#type' => 'hidden',
          '#value' => $item['mlid'],
        );
        $form[$mlid]['plid'] = array(
          '#type' => 'hidden',
          '#default_value' => $item['plid'],
        );
        // Build a list of operations.
        $operations = array();
        $operations['edit'] = array(
          'title' => t('Edit'),
          'href' => 'admin/structure/menu/item/' . $item['mlid'] . '/edit',
        );
        // Only items created by the menu module can be deleted.
        if ($item->access('delete')) {
          $operations['delete'] = array(
            'title' => t('Delete'),
            'href' => 'admin/structure/menu/item/' . $item['mlid'] . '/delete',
          );
        }
        // Set the reset column.
        elseif ($item->access('reset')) {
          $operations['reset'] = array(
            'title' => t('Reset'),
            'href' => 'admin/structure/menu/item/' . $item['mlid'] . '/reset',
          );
        }
        $form[$mlid]['operations'] = array(
          '#type' => 'operations',
          '#links' => $operations,
        );
      }

      if ($data['below']) {
        $this->buildOverviewTreeForm($data['below'], $delta);
      }
    }
    return $form;
  }

  /**
   * Submit handler for the menu overview form.
   *
   * This function takes great care in saving parent items first, then items
   * underneath them. Saving items in the incorrect order can break the menu tree.
   */
  protected function submitOverviewForm(array $complete_form, array &$form_state) {
    // Form API supports constructing and validating self-contained sections
    // within forms, but does not allow to handle the form section's submission
    // equally separated yet. Therefore, we use a $form_state key to point to
    // the parents of the form section.
    $parents = $form_state['menu_overview_form_parents'];
    $input = NestedArray::getValue($form_state['input'], $parents);
    $form = &NestedArray::getValue($complete_form, $parents);

    // When dealing with saving menu items, the order in which these items are
    // saved is critical. If a changed child item is saved before its parent,
    // the child item could be saved with an invalid path past its immediate
    // parent. To prevent this, save items in the form in the same order they
    // are sent, ensuring parents are saved first, then their children.
    // See http://drupal.org/node/181126#comment-632270
    $order = is_array($input) ? array_flip(array_keys($input)) : array();
    // Update our original form with the new order.
    $form = array_intersect_key(array_merge($order, $form), $form);

    $updated_items = array();
    $fields = array('weight', 'plid');
    foreach (element_children($form) as $mlid) {
      if (isset($form[$mlid]['#item'])) {
        $element = $form[$mlid];
        // Update any fields that have changed in this menu item.
        foreach ($fields as $field) {
          if ($element[$field]['#value'] != $element[$field]['#default_value']) {
            $element['#item'][$field] = $element[$field]['#value'];
            $updated_items[$mlid] = $element['#item'];
          }
        }
        // Hidden is a special case, the value needs to be reversed.
        if ($element['hidden']['#value'] != $element['hidden']['#default_value']) {
          // Convert to integer rather than boolean due to PDO cast to string.
          $element['#item']['hidden'] = $element['hidden']['#value'] ? 0 : 1;
          $updated_items[$mlid] = $element['#item'];
        }
      }
    }

    // Save all our changed items to the database.
    foreach ($updated_items as $item) {
      $item['customized'] = 1;
      $item->save();
    }
  }

}
