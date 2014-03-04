<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\filter\ManyToOne.
 */

namespace Drupal\views\Plugin\views\filter;

use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ManyToOneHelper;
use Drupal\Component\Annotation\PluginID;

/**
 * Complex filter to handle filtering for many to one relationships,
 * such as terms (many terms per node) or roles (many roles per user).
 *
 * The construct method needs to be overridden to provide a list of options;
 * alternately, the valueForm and adminSummary methods need to be overriden
 * to provide something that isn't just a select list.
 *
 * @ingroup views_filter_handlers
 *
 * @PluginID("many_to_one")
 */
class ManyToOne extends InOperator {

  /**
   * @var Drupal\views\ManyToOneHelper
   *
   * Stores the Helper object which handles the many_to_one complexity.
   */
  var $helper = NULL;

  /**
   * Overrides \Drupal\views\Plugin\views\filter\InOperator::init().
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->helper = new ManyToOneHelper($this);
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['operator']['default'] = 'or';
    $options['value']['default'] = array();

    if (isset($this->helper)) {
      $this->helper->defineOptions($options);
    }
    else {
      $helper = new ManyToOneHelper($this);
      $helper->defineOptions($options);
    }

    return $options;
  }

  function operators() {
    $operators = array(
      'or' => array(
        'title' => t('Is one of'),
        'short' => t('or'),
        'short_single' => t('='),
        'method' => 'opHelper',
        'values' => 1,
        'ensure_my_table' => 'helper',
      ),
      'and' => array(
        'title' => t('Is all of'),
        'short' => t('and'),
        'short_single' => t('='),
        'method' => 'opHelper',
        'values' => 1,
        'ensure_my_table' => 'helper',
      ),
      'not' => array(
        'title' => t('Is none of'),
        'short' => t('not'),
        'short_single' => t('<>'),
        'method' => 'opHelper',
        'values' => 1,
        'ensure_my_table' => 'helper',
      ),
    );
    // if the definition allows for the empty operator, add it.
    if (!empty($this->definition['allow empty'])) {
      $operators += array(
        'empty' => array(
          'title' => t('Is empty (NULL)'),
          'method' => 'opEmpty',
          'short' => t('empty'),
          'values' => 0,
        ),
        'not empty' => array(
          'title' => t('Is not empty (NOT NULL)'),
          'method' => 'opEmpty',
          'short' => t('not empty'),
          'values' => 0,
        ),
      );
    }

    return $operators;
  }

  var $value_form_type = 'select';
  protected function valueForm(&$form, &$form_state) {
    parent::valueForm($form, $form_state);

    if (empty($form_state['exposed'])) {
      $this->helper->buildOptionsForm($form, $form_state);
    }
  }

  /**
   * Override ensureMyTable so we can control how this joins in.
   * The operator actually has influence over joining.
   */
  public function ensureMyTable() {
    // Defer to helper if the operator specifies it.
    $info = $this->operators();
    if (isset($info[$this->operator]['ensure_my_table']) && $info[$this->operator]['ensure_my_table'] == 'helper') {
      return $this->helper->ensureMyTable();
    }

    return parent::ensureMyTable();
  }

  protected function opHelper() {
    if (empty($this->value)) {
      return;
    }
    $this->helper->addFilter();
  }

}
