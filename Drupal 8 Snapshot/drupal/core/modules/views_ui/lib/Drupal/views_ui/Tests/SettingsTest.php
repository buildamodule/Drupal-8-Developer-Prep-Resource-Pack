<?php

/**
 * @file
 * Contains \Drupal\views_ui\Tests\SettingsTest.
 */

namespace Drupal\views_ui\Tests;

/**
 * Tests the various settings in the views UI.
 */
class SettingsTest extends UITestBase {

  /**
   * Stores an admin user used by the different tests.
   *
   * @var Drupal\user\User
   */
  protected $adminUser;

  public static function getInfo() {
    return array(
      'name' => 'Settings functionality',
      'description' => 'Tests all ui related settings under admin/structure/views/settings.',
      'group' => 'Views UI',
    );
  }

  /**
   * Tests the settings for the edit ui.
   */
  function testEditUI() {
    $this->drupalLogin($this->adminUser);

    // Test the settings tab exists.
    $this->drupalGet('admin/structure/views');
    $this->assertLinkByHref('admin/structure/views/settings');

    // Configure to always show the master display.
    $edit = array(
      'ui_show_master_display' => TRUE,
    );
    $this->drupalPost('admin/structure/views/settings', $edit, t('Save configuration'));

    $view = array();
    $view['label'] = $this->randomName(16);
    $view['id'] = strtolower($this->randomName(16));
    $view['description'] = $this->randomName(16);
    $view['page[create]'] = TRUE;
    $view['page[title]'] = $this->randomName(16);
    $view['page[path]'] = $this->randomName(16);
    $this->drupalPost('admin/structure/views/add', $view, t('Save and edit'));

    // Configure to not always show the master display.
    // If you have a view without a page or block the master display should be
    // still shown.
    $edit = array(
      'ui_show_master_display' => FALSE,
    );
    $this->drupalPost('admin/structure/views/settings', $edit, t('Save configuration'));

    $view['page[create]'] = FALSE;
    $this->drupalPost('admin/structure/views/add', $view, t('Save and edit'));

    // Create a view with an additional display, so master should be hidden.
    $view['page[create]'] = TRUE;
    $view['id'] = strtolower($this->randomName());
    $this->drupalPost('admin/structure/views/add', $view, t('Save and edit'));

    $this->assertNoLink(t('Master'));

    // Configure to always show the advanced settings.
    // @todo It doesn't seem to be a way to test this as this works just on js.

    // Configure to show the embedable display.
    $edit = array(
      'ui_show_display_embed' => TRUE,
    );
    $this->drupalPost('admin/structure/views/settings', $edit, t('Save configuration'));

    $view['id'] = strtolower($this->randomName());
    $this->drupalPost('admin/structure/views/add', $view, t('Save and edit'));
    $this->assertFieldById('edit-displays-top-add-display-embed');

    $edit = array(
      'ui_show_display_embed' => FALSE,
    );
    $this->drupalPost('admin/structure/views/settings', $edit, t('Save configuration'));
    views_invalidate_cache();
    $this->drupalPost('admin/structure/views/add', $view, t('Save and edit'));
    $this->assertNoFieldById('edit-displays-top-add-display-embed');

    // Configure to hide/show the sql at the preview.
    $edit = array(
      'ui_show_sql_query_enabled' => FALSE,
    );
    $this->drupalPost('admin/structure/views/settings', $edit, t('Save configuration'));

    $view['id'] = strtolower($this->randomName());
    $this->drupalPost('admin/structure/views/add', $view, t('Save and edit'));

    $this->drupalPost(NULL, array(), t('Update preview'));
    $xpath = $this->xpath('//div[@class="views-query-info"]/pre');
    $this->assertEqual(count($xpath), 0, 'The views sql is hidden.');

    $edit = array(
      'ui_show_sql_query_enabled' => TRUE,
    );
    $this->drupalPost('admin/structure/views/settings', $edit, t('Save configuration'));

    $view['id'] = strtolower($this->randomName());
    $this->drupalPost('admin/structure/views/add', $view, t('Save and edit'));

    $this->drupalPost(NULL, array(), t('Update preview'));
    $xpath = $this->xpath('//div[@class="views-query-info"]//pre');
    $this->assertEqual(count($xpath), 1, 'The views sql is shown.');
    $this->assertFalse(strpos($xpath[0], 'db_condition_placeholder') !== FALSE, 'No placeholders are shown in the views sql.');
    $this->assertTrue(strpos($xpath[0], "node_field_data.status = '1'") !== FALSE, 'The placeholders in the views sql is replace by the actual value.');
  }

}
