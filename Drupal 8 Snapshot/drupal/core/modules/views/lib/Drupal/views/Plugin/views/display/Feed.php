<?php

/**
 * @file
 * Contains \Drupal\views\Plugin\views\display\Feed.
 */

namespace Drupal\views\Plugin\views\display;

use Drupal\views\ViewExecutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * The plugin that handles a feed, such as RSS or atom.
 *
 * @ingroup views_display_plugins
 *
 * @Plugin(
 *   id = "feed",
 *   title = @Translation("Feed"),
 *   help = @Translation("Display the view as a feed, such as an RSS feed."),
 *   uses_route = TRUE,
 *   admin = @Translation("Feed")
 * )
 */
class Feed extends PathPluginBase {

  /**
   * Whether the display allows the use of AJAX or not.
   *
   * @var bool
   */
  protected $ajaxEnabled = FALSE;

  /**
   * Whether the display allows the use of a pager or not.
   *
   * @var bool
   */
  protected $usesPager = FALSE;

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::initDisplay().
   */
  public function initDisplay(ViewExecutable $view, array &$display, array &$options = NULL) {
    parent::initDisplay($view, $display, $options);

    // Set the default row style. Ideally this would be part of the option
    // definition, but in this case it's dependent on the view's base table,
    // which we don't know until init().
    $row_plugins = views_fetch_plugin_names('row', $this->getType(), array($view->storage->get('base_table')));
    $default_row_plugin = key($row_plugins);
    if (empty($this->options['row']['type'])) {
      $this->options['row']['type'] = $default_row_plugin;
    }
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::getType().
   */
  protected function getType() {
    return 'feed';
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\PathPluginBase::execute().
   */
  public function execute() {
    parent::execute();

    $output = $this->view->render();

    if (empty($output)) {
      throw new NotFoundHttpException();
    }

    $response = $this->view->getResponse();

    $response->setContent($output);

    return $response;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\PathPluginBase::preview().
   */
  public function preview() {
    if (!empty($this->view->live_preview)) {
      return '<pre>' . check_plain($this->view->render()) . '</pre>';
    }

    return $this->view->render();
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\PathPluginBase::render().
   */
  public function render() {
    return $this->view->style_plugin->render($this->view->result);
  }

  /**
   * Overrides \Drupal\views\Plugin\views\displays\DisplayPluginBase::defaultableSections().
   */
  public function defaultableSections($section = NULL) {
    $sections = parent::defaultableSections($section);

    if (in_array($section, array('style', 'row'))) {
      return FALSE;
    }

    // Tell views our sitename_title option belongs in the title section.
    if ($section == 'title') {
      $sections[] = 'sitename_title';
    }
    elseif (!$section) {
      $sections['title'][] = 'sitename_title';
    }
    return $sections;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\PathPluginBase::defineOptions().
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['displays'] = array('default' => array());

    // Overrides for standard stuff.
    $options['style']['contains']['type']['default'] = 'rss';
    $options['style']['contains']['options']['default']  = array('description' => '');
    $options['sitename_title']['default'] = FALSE;
    $options['row']['contains']['type']['default'] = '';
    $options['defaults']['default']['style'] = FALSE;
    $options['defaults']['default']['row'] = FALSE;

    return $options;
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::optionsSummary().
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    // Since we're childing off the 'path' type, we'll still *call* our
    // category 'page' but let's override it so it says feed settings.
    $categories['page'] = array(
      'title' => t('Feed settings'),
      'column' => 'second',
      'build' => array(
        '#weight' => -10,
      ),
    );

    if ($this->getOption('sitename_title')) {
      $options['title']['value'] = t('Using the site name');
    }

    $displays = array_filter($this->getOption('displays'));
    if (count($displays) > 1) {
      $attach_to = t('Multiple displays');
    }
    elseif (count($displays) == 1) {
      $display = array_shift($displays);
      $displays = $this->view->storage->get('display');
      if (!empty($displays[$display])) {
        $attach_to = check_plain($displays[$display]['display_title']);
      }
    }

    if (!isset($attach_to)) {
      $attach_to = t('None');
    }

    $options['displays'] = array(
      'category' => 'page',
      'title' => t('Attach to'),
      'value' => $attach_to,
    );
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\PathPluginBase::buildOptionsForm().
   */
  public function buildOptionsForm(&$form, &$form_state) {
    // It is very important to call the parent function here.
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state['section']) {
      case 'title':
        $title = $form['title'];
        // A little juggling to move the 'title' field beyond our checkbox.
        unset($form['title']);
        $form['sitename_title'] = array(
          '#type' => 'checkbox',
          '#title' => t('Use the site name for the title'),
          '#default_value' => $this->getOption('sitename_title'),
        );
        $form['title'] = $title;
        $form['title']['#states'] = array(
          'visible' => array(
            ':input[name="sitename_title"]' => array('checked' => FALSE),
          ),
        );
        break;
      case 'displays':
        $form['#title'] .= t('Attach to');
        $displays = array();
        foreach ($this->view->storage->get('display') as $display_id => $display) {
          // @todo The display plugin should have display_title and id as well.
          if ($this->view->displayHandlers->has($display_id) && $this->view->displayHandlers->get($display_id)->acceptAttachments()) {
            $displays[$display_id] = $display['display_title'];
          }
        }
        $form['displays'] = array(
          '#type' => 'checkboxes',
          '#description' => t('The feed icon will be available only to the selected displays.'),
          '#options' => $displays,
          '#default_value' => $this->getOption('displays'),
        );
        break;
      case 'path':
        $form['path']['#description'] = t('This view will be displayed by visiting this path on your site. It is recommended that the path be something like "path/%/%/feed" or "path/%/%/rss.xml", putting one % in the path for each contextual filter you have defined in the view.');
    }
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::submitOptionsForm().
   */
  public function submitOptionsForm(&$form, &$form_state) {
    parent::submitOptionsForm($form, $form_state);
    switch ($form_state['section']) {
      case 'title':
        $this->setOption('sitename_title', $form_state['values']['sitename_title']);
        break;
      case 'displays':
        $this->setOption($form_state['section'], $form_state['values'][$form_state['section']]);
        break;
    }
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::attachTo().
   */
  public function attachTo(ViewExecutable $clone, $display_id) {
    $displays = $this->getOption('displays');
    if (empty($displays[$display_id])) {
      return;
    }

    // Defer to the feed style; it may put in meta information, and/or
    // attach a feed icon.
    $clone->setDisplay($this->display['id']);
    $clone->buildTitle();
    if ($plugin = $clone->display_handler->getPlugin('style')) {
      $plugin->attachTo($display_id, $this->getPath(), $clone->getTitle());
    }

    // Clean up.
    $clone->destroy();
    unset($clone);
  }

  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::usesLinkDisplay().
   */
  public function usesLinkDisplay() {
    return TRUE;
  }

}
