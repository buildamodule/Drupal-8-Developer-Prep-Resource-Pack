<?php

/**
 * @file
 * Contains \Drupal\views\Tests\Handler\FilterBooleanWebTest.
 */

namespace Drupal\views_ui\Tests;

/**
 * Tests the boolean filter UI.
 *
 * @see \Drupal\views\Plugin\views\filter\BooleanOperator
 */
class FilterBooleanWebTest extends UITestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_view');

  public static function getInfo() {
    return array(
      'name' => 'Filter: Boolean',
      'description' => 'Tests the boolean filter UI.',
      'group' => 'Views UI',
    );
  }

  /**
   * Tests the filter boolean UI.
   */
  public function testFilterBooleanUI() {
    $this->drupalPost('admin/structure/views/nojs/add-item/test_view/default/filter', array('name[views_test_data.status]' => TRUE), t('Add and configure @handler', array('@handler' => t('filter criteria'))));

    $this->drupalPost(NULL, array(), t('Expose filter'));
    $this->drupalPost(NULL, array(), t('Grouped filters'));

    $edit = array();
    $edit['options[group_info][group_items][1][title]'] = 'Published';
    $edit['options[group_info][group_items][1][operator]'] = '=';
    $edit['options[group_info][group_items][1][value]'] = 1;
    $edit['options[group_info][group_items][2][title]'] = 'Not published';
    $edit['options[group_info][group_items][2][operator]'] = '=';
    $edit['options[group_info][group_items][2][value]'] = 0;
    $edit['options[group_info][group_items][3][title]'] = 'Not published2';
    $edit['options[group_info][group_items][3][operator]'] = '!=';
    $edit['options[group_info][group_items][3][value]'] = 1;

    $this->drupalPost(NULL, $edit, t('Apply'));

    $this->drupalGet('admin/structure/views/nojs/config-item/test_view/default/filter/status');

    $result = $this->xpath('//input[@name="options[group_info][group_items][1][value]"]');
    $this->assertEqual((int) $result[1]->attributes()->checked, 'checked');
    $result = $this->xpath('//input[@name="options[group_info][group_items][2][value]"]');
    $this->assertEqual((int) $result[2]->attributes()->checked, 'checked');
    $result = $this->xpath('//input[@name="options[group_info][group_items][3][value]"]');
    $this->assertEqual((int) $result[1]->attributes()->checked, 'checked');
  }

}
