<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\area\Result.
 */

namespace Drupal\views\Plugin\views\area;

use Drupal\Component\Annotation\PluginID;

/**
 * Views area handler to display some configurable result summary.
 *
 * @ingroup views_area_handlers
 *
 * @PluginID("result")
 */
class Result extends AreaPluginBase {

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['content'] = array(
      'default' => 'Displaying @start - @end of @total',
      'translatable' => TRUE,
    );

    return $options;
  }

  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);
    $item_list = array(
      '#theme' => 'item_list',
      '#items' => array(
        '@start -- the initial record number in the set',
        '@end -- the last record number in the set',
        '@total -- the total records in the set',
        '@label -- the human-readable name of the view',
        '@per_page -- the number of items per page',
        '@current_page -- the current page number',
        '@current_record_count -- the current page record count',
        '@page_count -- the total page count',
      ),
    );
    $list = drupal_render($item_list);
    $form['content'] = array(
      '#title' => t('Display'),
      '#type' => 'textarea',
      '#rows' => 3,
      '#default_value' => $this->options['content'],
      '#description' => t('You may use HTML code in this field. The following tokens are supported:') . $list,
    );
  }


  /**
   * Implements \Drupal\views\Plugin\views\area\AreaPluginBase::render().
   */
  public function render($empty = FALSE) {
    // Must have options and does not work on summaries.
    if (!isset($this->options['content']) || $this->view->plugin_name == 'default_summary') {
      return array();
    }
    $output = '';
    $format = $this->options['content'];
    // Calculate the page totals.
    $current_page = (int) $this->view->getCurrentPage() + 1;
    $per_page = (int) $this->view->getItemsPerPage();
    $count = count($this->view->result);
    // @TODO: Maybe use a possible is views empty functionality.
    // Not every view has total_rows set, use view->result instead.
    $total = isset($this->view->total_rows) ? $this->view->total_rows : count($this->view->result);
    $label = check_plain($this->view->storage->label());
    if ($per_page === 0) {
      $page_count = 1;
      $start = 1;
      $end = $total;
    }
    else {
      $page_count = (int) ceil($total / $per_page);
      $total_count = $current_page * $per_page;
      if ($total_count > $total) {
        $total_count = $total;
      }
      $start = ($current_page - 1) * $per_page + 1;
      $end = $total_count;
    }
    $current_record_count = ($end - $start) + 1;
    // Get the search information.
    $items = array('start', 'end', 'total', 'label', 'per_page', 'current_page', 'current_record_count', 'page_count');
    $replacements = array();
    foreach ($items as $item) {
      $replacements["@$item"] = ${$item};
    }
    // Send the output.
    if (!empty($total)) {
      $output .= filter_xss_admin(str_replace(array_keys($replacements), array_values($replacements), $format));
    }
    // Return as render array.
    return array(
      '#markup' => $output,
    );
  }

}
