<?php

/**
 * @file
 * Contains \Drupal\statistics\Plugin\Block\StatisticsPopularBlock.
 */

namespace Drupal\statistics\Plugin\Block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Provides a 'Popular content' block.
 *
 * @Plugin(
 *   id = "statistics_popular_block",
 *   admin_label = @Translation("Popular content"),
 *   module = "statistics"
 * )
 */
class StatisticsPopularBlock extends BlockBase {

  /**
   * Number of day's top views to display.
   *
   * @var int
   */
  protected $day_list;

  /**
   * Number of all time views to display.
   *
   * @var int
   */
  protected $all_time_list;

  /**
   * Number of most recent views to display.
   *
   * @var int
   */
  protected $last_list;

  /**
   * Overrides \Drupal\block\BlockBase::settings().
   */
  public function settings() {
    return array(
      'top_day_num' => 0,
      'top_all_num' => 0,
      'top_last_num' => 0
    );
  }

    /**
   * Overrides \Drupal\block\BlockBase::access().
   */
  public function access() {
    if (user_access('access content')) {
      $daytop = $this->configuration['top_day_num'];
      if (!$daytop || !($result = statistics_title_list('daycount', $daytop)) || !($this->day_list = node_title_list($result, t("Today's:")))) {
        return FALSE;
      }
      $alltimetop = $this->configuration['top_all_num'];
      if (!$alltimetop || !($result = statistics_title_list('totalcount', $alltimetop)) || !($this->all_time_list = node_title_list($result, t('All time:')))) {
        return FALSE;
      }
      $lasttop = $this->configuration['top_last_num'];
      if (!$lasttop || !($result = statistics_title_list('timestamp', $lasttop)) || !($this->last_list = node_title_list($result, t('Last viewed:')))) {
        return FALSE;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockForm().
   */
  public function blockForm($form, &$form_state) {
    // Popular content block settings.
    $numbers = array('0' => t('Disabled')) + drupal_map_assoc(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 25, 30, 40));
    $form['statistics_block_top_day_num'] = array(
     '#type' => 'select',
     '#title' => t("Number of day's top views to display"),
     '#default_value' => $this->configuration['top_day_num'],
     '#options' => $numbers,
     '#description' => t('How many content items to display in "day" list.'),
    );
    $form['statistics_block_top_all_num'] = array(
      '#type' => 'select',
      '#title' => t('Number of all time views to display'),
      '#default_value' => $this->configuration['top_all_num'],
      '#options' => $numbers,
      '#description' => t('How many content items to display in "all time" list.'),
    );
    $form['statistics_block_top_last_num'] = array(
      '#type' => 'select',
      '#title' => t('Number of most recent views to display'),
      '#default_value' => $this->configuration['top_last_num'],
      '#options' => $numbers,
      '#description' => t('How many content items to display in "recently viewed" list.'),
    );
    return $form;
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockSubmit().
   */
  public function blockSubmit($form, &$form_state) {
    $this->configuration['top_day_num'] = $form_state['values']['statistics_block_top_day_num'];
    $this->configuration['top_all_num'] = $form_state['values']['statistics_block_top_all_num'];
    $this->configuration['top_last_num'] = $form_state['values']['statistics_block_top_last_num'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = array();

    if ($this->day_list) {
      $content['top_day'] = $this->day_list;
      $content['top_day']['#suffix'] = '<br />';
    }

    if ($this->all_time_list) {
      $content['top_all'] = $this->all_time_list;
      $content['top_all']['#suffix'] = '<br />';
    }

    if ($this->last_list) {
      $content['top_last'] = $this->last_list;
      $content['top_last']['#suffix'] = '<br />';
    }

    return $content;
  }

}
