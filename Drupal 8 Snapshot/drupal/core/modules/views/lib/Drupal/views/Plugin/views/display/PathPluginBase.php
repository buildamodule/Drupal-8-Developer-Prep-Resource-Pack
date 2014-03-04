<?php

/**
 * @file
 * Contains \Drupal\views\Plugin\views\display\PathPluginBase.
 */

namespace Drupal\views\Plugin\views\display;

use Drupal\views\Views;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The base display plugin for path/callbacks. This is used for pages and feeds.
 */
abstract class PathPluginBase extends DisplayPluginBase implements DisplayRouterInterface {

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::hasPath().
   */
  public function hasPath() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    $bits = explode('/', $this->getOption('path'));
    if ($this->isDefaultTabPath()) {
      array_pop($bits);
    }
    return implode('/', $bits);
  }

  /**
   * Determines if this display's path is a default tab.
   *
   * @return bool
   *   TRUE if the display path is for a default tab, FALSE otherwise.
   */
  protected function isDefaultTabPath() {
    $menu = $this->getOption('menu');
    $tab_options = $this->getOption('tab_options');
    return $menu['type'] == 'default tab' && !empty($tab_options['type']) && $tab_options['type'] != 'none';
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase:defineOptions().
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['path'] = array('default' => '');

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function collectRoutes(RouteCollection $collection) {
    $view_id = $this->view->storage->id();
    $display_id = $this->display['id'];

    $defaults = array(
      '_controller' => 'Drupal\views\Routing\ViewPageController::handle',
      'view_id' => $view_id,
      'display_id' => $display_id,
    );

    // @todo How do we apply argument validation?
    $bits = explode('/', $this->getOption('path'));
    // @todo Figure out validation/argument loading.
    // Replace % with %views_arg for menu autoloading and add to the
    // page arguments so the argument actually comes through.
    $arg_counter = 0;

    $this->view->initHandlers();
    $view_arguments = $this->view->argument;

    $argument_ids = array_keys($view_arguments);
    $total_arguments = count($argument_ids);

    // Replace arguments in the views UI (defined via %) with parameters in
    // routes (defined via {}). As a name for the parameter use arg_$key, so
    // it can be pulled in the views controller from the request.
    foreach ($bits as $pos => $bit) {
      if ($bit == '%') {
        // Generate the name of the parameter using the key of the argument
        // handler.
        $arg_id = 'arg_' . $argument_ids[$arg_counter++];
        $bits[$pos] = '{' . $arg_id . '}';
      }
    }

    // Add missing arguments not defined in the path, but added as handler.
    while (($total_arguments - $arg_counter) > 0) {
      $arg_id = 'arg_' . $argument_ids[$arg_counter++];
      $bit = '{' . $arg_id . '}';
      // In contrast to the previous loop add the defaults here, as % was not
      // specified, which means the argument is optional.
      $defaults[$arg_id] = NULL;
      $bits[] = $bit;
    }

    // If this is to be a default tab, create the route for the parent path.
    if ($this->isDefaultTabPath()) {
      $bit = array_pop($bits);
      if ($bit == '%views_arg' || empty($bits)) {
        $bits[] = $bit;
      }
    }

    $route_path = '/' . implode('/', $bits);

    $route = new Route($route_path, $defaults);

    // Add access check parameters to the route.
    $access_plugin = $this->getPlugin('access');
    if (!isset($access_plugin)) {
      // @todo Do we want to support a default plugin in getPlugin itself?
      $access_plugin = Views::pluginManager('access')->createInstance('none');
    }
    $access_plugin->alterRouteDefinition($route);

    $collection->add("view.$view_id.$display_id", $route);
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::executeHookMenu().
   */
  public function executeHookMenu($callbacks) {
    $items = array();
    // Replace % with the link to our standard views argument loader
    // views_arg_load -- which lives in views.module.

    $bits = explode('/', $this->getOption('path'));
    $page_arguments = array($this->view->storage->id(), $this->display['id']);
    $this->view->initHandlers();
    $view_arguments = $this->view->argument;

    // Replace % with %views_arg for menu autoloading and add to the
    // page arguments so the argument actually comes through.
    foreach ($bits as $pos => $bit) {
      if ($bit == '%') {
        $argument = array_shift($view_arguments);
        if (!empty($argument->options['specify_validation']) && $argument->options['validate']['type'] != 'none') {
          $bits[$pos] = '%views_arg';
        }
        $page_arguments[] = $pos;
      }
    }

    $path = implode('/', $bits);

    if ($path) {
      $items[$path] = array(
        'route_name' => "view.{$this->view->storage->id()}.{$this->display['id']}",
        // Identify URL embedded arguments and correlate them to a handler.
        'load arguments'  => array($this->view->storage->id(), $this->display['id'], '%index'),
      );
      $menu = $this->getOption('menu');
      if (empty($menu)) {
        $menu = array('type' => 'none');
      }
      // Set the title and description if we have one.
      if ($menu['type'] != 'none') {
        $items[$path]['title'] = $menu['title'];
        $items[$path]['description'] = $menu['description'];
      }

      if (isset($menu['weight'])) {
        $items[$path]['weight'] = intval($menu['weight']);
      }

      switch ($menu['type']) {
        case 'none':
        default:
          $items[$path]['type'] = MENU_CALLBACK;
          break;
        case 'normal':
          $items[$path]['type'] = MENU_NORMAL_ITEM;
          // Insert item into the proper menu.
          $items[$path]['menu_name'] = $menu['name'];
          break;
        case 'tab':
          $items[$path]['type'] = MENU_LOCAL_TASK;
          break;
        case 'default tab':
          $items[$path]['type'] = MENU_DEFAULT_LOCAL_TASK;
          break;
      }

      // Add context for contextual links.
      // @see menu_contextual_links()
      if (!empty($menu['context'])) {
        $items[$path]['context'] = MENU_CONTEXT_INLINE;
      }

      // If this is a 'default' tab, check to see if we have to create the
      // parent menu item.
      if ($this->isDefaultTabPath()) {
        $tab_options = $this->getOption('tab_options');

        $bits = explode('/', $path);
        // Remove the last piece.
        $bit = array_pop($bits);

        // we can't do this if they tried to make the last path bit variable.
        // @todo: We can validate this.
        if ($bit != '%views_arg' && !empty($bits)) {
          // Assign the route name to the parent route, not the default tab.
          $default_route_name = $items[$path]['route_name'];
          unset($items[$path]['route_name']);

          $default_path = implode('/', $bits);
          $items[$default_path] = array(
            // Default views page entry.
            // Identify URL embedded arguments and correlate them to a
            // handler.
            'load arguments'  => array($this->view->storage->id(), $this->display['id'], '%index'),
            'title' => $tab_options['title'],
            'description' => $tab_options['description'],
            'menu_name' => $tab_options['name'],
            'route_name' => $default_route_name,
          );
          switch ($tab_options['type']) {
            default:
            case 'normal':
              $items[$default_path]['type'] = MENU_NORMAL_ITEM;
              break;
            case 'tab':
              $items[$default_path]['type'] = MENU_LOCAL_TASK;
              break;
          }
          if (isset($tab_options['weight'])) {
            $items[$default_path]['weight'] = intval($tab_options['weight']);
          }
        }
      }
    }

    return $items;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::execute().
   */
  public function execute() {
    // Prior to this being called, the $view should already be set to this
    // display, and arguments should be set on the view.
    $this->view->build();

    if (!empty($this->view->build_info['fail'])) {
      throw new NotFoundHttpException();
    }

    if (!empty($this->view->build_info['denied'])) {
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::optionsSummary().
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $categories['page'] = array(
      'title' => t('Page settings'),
      'column' => 'second',
      'build' => array(
        '#weight' => -10,
      ),
    );

    $path = strip_tags($this->getOption('path'));

    if (empty($path)) {
      $path = t('No path is set');
    }
    else {
      $path = '/' . $path;
    }

    $options['path'] = array(
      'category' => 'page',
      'title' => t('Path'),
      'value' => views_ui_truncate($path, 24),
    );
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::buildOptionsForm().
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state['section']) {
      case 'path':
        $form['#title'] .= t('The menu path or URL of this view');
        $form['path'] = array(
          '#type' => 'textfield',
          '#description' => t('This view will be displayed by visiting this path on your site. You may use "%" in your URL to represent values that will be used for contextual filters: For example, "node/%/feed".'),
          '#default_value' => $this->getOption('path'),
          '#field_prefix' => '<span dir="ltr">' . url(NULL, array('absolute' => TRUE)),
          '#field_suffix' => '</span>&lrm;',
          '#attributes' => array('dir' => 'ltr'),
        );
        break;
    }
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::validateOptionsForm().
   */
  public function validateOptionsForm(&$form, &$form_state) {
    parent::validateOptionsForm($form, $form_state);

    if ($form_state['section'] == 'path') {
      if (strpos($form_state['values']['path'], '%') === 0) {
        form_error($form['path'], t('"%" may not be used for the first segment of a path.'));
      }

      // Automatically remove '/' and trailing whitespace from path.
      $form_state['values']['path'] = trim($form_state['values']['path'], '/ ');
    }
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::submitOptionsForm().
   */
  public function submitOptionsForm(&$form, &$form_state) {
    parent::submitOptionsForm($form, $form_state);

    if ($form_state['section'] == 'path') {
      $this->setOption('path', $form_state['values']['path']);
    }
  }

}
