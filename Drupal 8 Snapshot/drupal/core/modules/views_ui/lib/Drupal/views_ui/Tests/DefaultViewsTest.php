<?php

/**
 * @file
 * Contains \Drupal\views_ui\Tests\DefaultViewsTest.
 */

namespace Drupal\views_ui\Tests;

/**
 * Tests enabling, disabling, and reverting default views via the listing page.
 */
class DefaultViewsTest extends UITestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_view_status', 'test_page_display_menu', 'test_page_display_arguments');

  public static function getInfo() {
    return array(
      'name' => 'Default views functionality',
      'description' => 'Test enabling, disabling, and reverting default views via the listing page.',
      'group' => 'Views UI',
    );
  }

  /**
   * Tests default views.
   */
  function testDefaultViews() {
    // Make sure the view starts off as disabled (does not appear on the listing
    // page).
    $edit_href = 'admin/structure/views/view/glossary';
    $this->drupalGet('admin/structure/views');
    // @todo Disabled default views do now appear on the front page. Test this
    // behavior with templates instead.
    // $this->assertNoLinkByHref($edit_href);

    // Enable the view, and make sure it is now visible on the main listing
    // page.
    $this->drupalGet('admin/structure/views');
    $this->clickViewsOperationLink(t('Enable'), '/glossary/');
    $this->assertUrl('admin/structure/views');
    $this->assertLinkByHref($edit_href);

    // It should not be possible to revert the view yet.
    // @todo Figure out how to handle this with the new configuration system.
    // $this->assertNoLink(t('Revert'));
    // $revert_href = 'admin/structure/views/view/glossary/revert';
    // $this->assertNoLinkByHref($revert_href);

    // Edit the view and change the title. Make sure that the new title is
    // displayed.
    $new_title = $this->randomName(16);
    $edit = array('title' => $new_title);
    $this->drupalPost('admin/structure/views/nojs/display/glossary/page_1/title', $edit, t('Apply'));
    $this->drupalPost('admin/structure/views/view/glossary/edit/page_1', array(), t('Save'));
    $this->drupalGet('glossary');
    $this->assertResponse(200);
    $this->assertText($new_title);

    // Save another view in the UI.
    $this->drupalPost('admin/structure/views/nojs/display/archive/page_1/title', array(), t('Apply'));
    $this->drupalPost('admin/structure/views/view/archive/edit/page_1', array(), t('Save'));

    // Check there is an enable link. i.e. The view has not been enabled after
    // editing.
    $this->drupalGet('admin/structure/views');
    $this->assertLinkByHref('admin/structure/views/view/archive/enable');

    // It should now be possible to revert the view. Do that, and make sure the
    // view title we added above no longer is displayed.
    // $this->drupalGet('admin/structure/views');
    // $this->assertLink(t('Revert'));
    // $this->assertLinkByHref($revert_href);
    // $this->drupalPost($revert_href, array(), t('Revert'));
    // $this->drupalGet('glossary');
    // $this->assertNoText($new_title);

    // Clone the view and check that the normal schema of cloned views is used.
    $this->drupalGet('admin/structure/views');
    $this->clickViewsOperationLink(t('Clone'), '/glossary');
    $edit = array(
      'id' => 'clone_of_glossary',
    );
    $this->assertTitle(t('Clone of @label | @site-name', array('@label' => 'Glossary', '@site-name' => \Drupal::config('system.site')->get('name'))));
    $this->drupalPost(NULL, $edit, t('Clone'));
    $this->assertUrl('admin/structure/views/view/clone_of_glossary', array(), 'The normal cloning name schema is applied.');

    // Clone a view and set a custom name.
    $this->drupalGet('admin/structure/views');
    $this->clickViewsOperationLink(t('Clone'), '/glossary');
    $random_name = strtolower($this->randomName());
    $this->drupalPost(NULL, array('id' => $random_name), t('Clone'));
    $this->assertUrl("admin/structure/views/view/$random_name", array(), 'The custom view name got saved.');

    // Now disable the view, and make sure it stops appearing on the main view
    // listing page but instead goes back to displaying on the disabled views
    // listing page.
    // @todo Test this behavior with templates instead.
    $this->drupalGet('admin/structure/views');
    $this->clickViewsOperationLink(t('Disable'), '/glossary/');
    // $this->assertUrl('admin/structure/views');
    // $this->assertNoLinkByHref($edit_href);
    // The easiest way to verify it appears on the disabled views listing page
    // is to try to click the "enable" link from there again.
    $this->drupalGet('admin/structure/views');
    $this->clickViewsOperationLink(t('Enable'), '/glossary/');
    $this->assertUrl('admin/structure/views');
    $this->assertLinkByHref($edit_href);

    // Test deleting a view.
    $this->drupalGet('admin/structure/views');
    $this->clickViewsOperationLink(t('Delete'), '/glossary/');
    // Submit the confirmation form.
    $this->drupalPost(NULL, array(), t('Delete'));
    // Ensure the view is no longer listed.
    $this->assertUrl('admin/structure/views');
    $this->assertNoLinkByHref($edit_href);
    // Ensure the view is no longer available.
    $this->drupalGet($edit_href);
    $this->assertResponse(404);
    $this->assertText('Page not found');
  }

  /**
   * Tests that enabling views moves them to the correct table.
   */
  function testSplitListing() {
    // Build a re-usable xpath query.
    $xpath = '//div[@id="views-entity-list"]/div[@class = :status]/table//tr[@title = :title]';
    $arguments = array(
      ':status' => 'views-list-section enabled',
      ':title' => t('Machine name: test_view_status'),
    );

    $this->drupalGet('admin/structure/views');

    $elements = $this->xpath($xpath, $arguments);
    $this->assertIdentical(count($elements), 0, 'A disabled view is not found in the enabled views table.');

    $arguments[':status'] = 'views-list-section disabled';
    $elements = $this->xpath($xpath, $arguments);
    $this->assertIdentical(count($elements), 1, 'A disabled view is found in the disabled views table.');

    // Enable the view.
    $this->clickViewsOperationLink(t('Enable'), '/test_view_status/');

    $elements = $this->xpath($xpath, $arguments);
    $this->assertIdentical(count($elements), 0, 'After enabling a view, it is not found in the disabled views table.');

    $arguments[':status'] = 'views-list-section enabled';
    $elements = $this->xpath($xpath, $arguments);
    $this->assertIdentical(count($elements), 1, 'After enabling a view, it is found in the enabled views table.');

    // Attempt to disable the view by path directly, with no token.
    $this->drupalGet('admin/structure/views/view/test_view_status/disable');
    $this->assertResponse(403);
  }

  /**
   * Tests that page displays show the correct path.
   */
  public function testPathDestination() {
    $this->drupalGet('admin/structure/views');

    // Check that links to views on default tabs are rendered correctly.
    $this->assertLinkByHref('test_page_display_menu');
    $this->assertNoLinkByHref('test_page_display_menu/default');
    $this->assertLinkByHref('test_page_display_menu/local');

    // Check that a dynamic path is shown as text.
    $this->assertRaw('test_route_with_suffix/%/suffix');
    $this->assertNoLinkByHref(url('test_route_with_suffix/%/suffix'));
  }

  /**
   * Click a link to perform an operation on a view.
   *
   * In general, we expect lots of links titled "enable" or "disable" on the
   * various views listing pages, and they might have tokens in them. So we
   * need special code to find the correct one to click.
   *
   * @param $label
   *   Text between the anchor tags of the desired link.
   * @param $unique_href_part
   *   A unique string that is expected to occur within the href of the desired
   *   link. For example, if the link URL is expected to look like
   *   "admin/structure/views/view/glossary/...", then "/glossary/" could be
   *   passed as the expected unique string.
   *
   * @return
   *   The page content that results from clicking on the link, or FALSE on
   *   failure. Failure also results in a failed assertion.
   */
  function clickViewsOperationLink($label, $unique_href_part) {
    $links = $this->xpath('//a[normalize-space(text())=:label]', array(':label' => $label));
    foreach ($links as $link_index => $link) {
      $position = strpos($link['href'], $unique_href_part);
      if ($position !== FALSE) {
        $index = $link_index;
        break;
      }
    }
    $this->assertTrue(isset($index), format_string('Link to "@label" containing @part found.', array('@label' => $label, '@part' => $unique_href_part)));
    if (isset($index)) {
      return $this->clickLink($label, $index);
    }
    else {
      return FALSE;
    }
  }

}
