<?php

/**
 * @file
 * Definition of Drupal\aggregator\Tests\ImportOpmlTest.
 */

namespace Drupal\aggregator\Tests;

/**
 * Tests importing feeds from OPML functionality for the Aggregator module.
 */
class ImportOpmlTest extends AggregatorTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('block');

  public static function getInfo() {
    return array(
      'name' => 'Import feeds from OPML functionality',
      'description' => 'Test OPML import.',
      'group' => 'Aggregator',
    );
  }

  function setUp() {
    parent::setUp();

    $admin_user = $this->drupalCreateUser(array('administer news feeds', 'access news feeds', 'create article content', 'administer blocks'));
    $this->drupalLogin($admin_user);
  }

  /**
   * Opens OPML import form.
   */
  function openImportForm() {
    db_delete('aggregator_category')->execute();

    $category = $this->randomName(10);
    $cid = db_insert('aggregator_category')
      ->fields(array(
        'title' => $category,
        'description' => '',
      ))
      ->execute();

    // Enable the help block.
    $this->drupalPlaceBlock('system_help_block', array('region' => 'help'));

    $this->drupalGet('admin/config/services/aggregator/add/opml');
    $this->assertText('A single OPML document may contain a collection of many feeds.', 'Found OPML help text.');
    $this->assertField('files[upload]', 'Found file upload field.');
    $this->assertField('remote', 'Found Remote URL field.');
    $this->assertField('refresh', '', 'Found Refresh field.');
    $this->assertFieldByName("category[$cid]", $cid, 'Found category field.');
  }

  /**
   * Submits form filled with invalid fields.
   */
  function validateImportFormFields() {
    $before = db_query('SELECT COUNT(*) FROM {aggregator_feed}')->fetchField();

    $edit = array();
    $this->drupalPost('admin/config/services/aggregator/add/opml', $edit, t('Import'));
    $this->assertRaw(t('You must <em>either</em> upload a file or enter a URL.'), 'Error if no fields are filled.');

    $path = $this->getEmptyOpml();
    $edit = array(
      'files[upload]' => $path,
      'remote' => file_create_url($path),
    );
    $this->drupalPost('admin/config/services/aggregator/add/opml', $edit, t('Import'));
    $this->assertRaw(t('You must <em>either</em> upload a file or enter a URL.'), 'Error if both fields are filled.');

    $edit = array('remote' => 'invalidUrl://empty');
    $this->drupalPost('admin/config/services/aggregator/add/opml', $edit, t('Import'));
    $this->assertText(t('The URL invalidUrl://empty is not valid.'), 'Error if the URL is invalid.');

    $after = db_query('SELECT COUNT(*) FROM {aggregator_feed}')->fetchField();
    $this->assertEqual($before, $after, 'No feeds were added during the three last form submissions.');
  }

  /**
   * Submits form with invalid, empty, and valid OPML files.
   */
  function submitImportForm() {
    $before = db_query('SELECT COUNT(*) FROM {aggregator_feed}')->fetchField();

    $form['files[upload]'] = $this->getInvalidOpml();
    $this->drupalPost('admin/config/services/aggregator/add/opml', $form, t('Import'));
    $this->assertText(t('No new feed has been added.'), 'Attempting to upload invalid XML.');

    $edit = array('remote' => file_create_url($this->getEmptyOpml()));
    $this->drupalPost('admin/config/services/aggregator/add/opml', $edit, t('Import'));
    $this->assertText(t('No new feed has been added.'), 'Attempting to load empty OPML from remote URL.');

    $after = db_query('SELECT COUNT(*) FROM {aggregator_feed}')->fetchField();
    $this->assertEqual($before, $after, 'No feeds were added during the two last form submissions.');

    db_delete('aggregator_feed')->execute();
    db_delete('aggregator_category')->execute();
    db_delete('aggregator_category_feed')->execute();

    $category = $this->randomName(10);
    db_insert('aggregator_category')
      ->fields(array(
        'cid' => 1,
        'title' => $category,
        'description' => '',
      ))
      ->execute();

    $feeds[0] = $this->getFeedEditArray();
    $feeds[1] = $this->getFeedEditArray();
    $feeds[2] = $this->getFeedEditArray();
    $edit = array(
      'files[upload]' => $this->getValidOpml($feeds),
      'refresh'       => '900',
      'category[1]'   => $category,
    );
    $this->drupalPost('admin/config/services/aggregator/add/opml', $edit, t('Import'));
    $this->assertRaw(t('A feed with the URL %url already exists.', array('%url' => $feeds[0]['url'])), 'Verifying that a duplicate URL was identified');
    $this->assertRaw(t('A feed named %title already exists.', array('%title' => $feeds[1]['title'])), 'Verifying that a duplicate title was identified');

    $after = db_query('SELECT COUNT(*) FROM {aggregator_feed}')->fetchField();
    $this->assertEqual($after, 2, 'Verifying that two distinct feeds were added.');

    $feeds_from_db = db_query("SELECT f.title, f.url, f.refresh, cf.cid FROM {aggregator_feed} f LEFT JOIN {aggregator_category_feed} cf ON f.fid = cf.fid");
    $refresh = $category = TRUE;
    foreach ($feeds_from_db as $feed) {
      $title[$feed->url] = $feed->title;
      $url[$feed->title] = $feed->url;
      $category = $category && $feed->cid == 1;
      $refresh = $refresh && $feed->refresh == 900;
    }

    $this->assertEqual($title[$feeds[0]['url']], $feeds[0]['title'], 'First feed was added correctly.');
    $this->assertEqual($url[$feeds[1]['title']], $feeds[1]['url'], 'Second feed was added correctly.');
    $this->assertTrue($refresh, 'Refresh times are correct.');
    $this->assertTrue($category, 'Categories are correct.');
  }

  /**
   * Tests the import of an OPML file.
   */
  function testOpmlImport() {
    $this->openImportForm();
    $this->validateImportFormFields();
    $this->submitImportForm();
  }
}
