<?php

/**
 * @file
 * Definition of Drupal\aggregator\Tests\AggregatorTestBase.
 */

namespace Drupal\aggregator\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;
use Drupal\aggregator\Plugin\Core\Entity\Feed;

/**
 * Defines a base class for testing the Aggregator module.
 */
abstract class AggregatorTestBase extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'aggregator', 'aggregator_test', 'views');

  function setUp() {
    parent::setUp();

    // Create an Article node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
    }

    $web_user = $this->drupalCreateUser(array('administer news feeds', 'access news feeds', 'create article content'));
    $this->drupalLogin($web_user);
  }

  /**
   * Creates an aggregator feed.
   *
   * This method simulates the form submission on path
   * admin/config/services/aggregator/add/feed.
   *
   * @param $feed_url
   *   (optional) If given, feed will be created with this URL, otherwise
   *   /rss.xml will be used. Defaults to NULL.
   * @param array $edit
   *   Array with additional form fields.
   *
   * @return \Drupal\aggregator\Plugin\Core\Entity\Feed $feed
   *   Full feed object if possible.
   *
   * @see getFeedEditArray()
   */
  function createFeed($feed_url = NULL, array $edit = array()) {
    $edit = $this->getFeedEditArray($feed_url, $edit);
    $this->drupalPost('admin/config/services/aggregator/add/feed', $edit, t('Save'));
    $this->assertRaw(t('The feed %name has been added.', array('%name' => $edit['title'])), format_string('The feed !name has been added.', array('!name' => $edit['title'])));

    $fid = db_query("SELECT fid FROM {aggregator_feed} WHERE title = :title AND url = :url", array(':title' => $edit['title'], ':url' => $edit['url']))->fetchField();
    $this->assertTrue(!empty($fid), 'The feed found in database.');
    return aggregator_feed_load($fid);
  }

  /**
   * Deletes an aggregator feed.
   *
   * @param \Drupal\aggregator\Plugin\Core\Entity\Feed $feed
   *   Feed object representing the feed.
   */
  function deleteFeed(Feed $feed) {
    $this->drupalPost('admin/config/services/aggregator/delete/feed/' . $feed->id(), array(), t('Delete'));
    $this->assertRaw(t('The feed %title has been deleted.', array('%title' => $feed->label())), 'Feed deleted successfully.');
  }

  /**
   * Returns a randomly generated feed edit array.
   *
   * @param $feed_url
   *   (optional) If given, feed will be created with this URL, otherwise
   *   /rss.xml will be used. Defaults to NULL.
   * @param array $edit
   *   Array with additional form fields.
   *
   * @return
   *   A feed array.
   */
  function getFeedEditArray($feed_url = NULL, array $edit = array()) {
    $feed_name = $this->randomName(10);
    if (!$feed_url) {
      $feed_url = url('rss.xml', array(
        'query' => array('feed' => $feed_name),
        'absolute' => TRUE,
      ));
    }
    $edit += array(
      'title' => $feed_name,
      'url' => $feed_url,
      'refresh' => '900',
    );
    return $edit;
  }

  /**
   * Returns a randomly generated feed edit object.
   *
   * @param string $feed_url
   *   (optional) If given, feed will be created with this URL, otherwise
   *   /rss.xml will be used. Defaults to NULL.
   * @param array $values
   *   (optional) Default values to initialize object properties with.
   *
   * @return \Drupal\aggregator\Plugin\Core\Entity\Feed
   *   A feed object.
   */
  function getFeedEditObject($feed_url = NULL, array $values = array()) {
    $feed_name = $this->randomName(10);
    if (!$feed_url) {
      $feed_url = url('rss.xml', array(
        'query' => array('feed' => $feed_name),
        'absolute' => TRUE,
      ));
    }
    $values += array(
      'title' => $feed_name,
      'url' => $feed_url,
      'refresh' => '900',
    );
    return entity_create('aggregator_feed', $values);
  }

  /**
   * Returns the count of the randomly created feed array.
   *
   * @return
   *   Number of feed items on default feed created by createFeed().
   */
  function getDefaultFeedItemCount() {
    // Our tests are based off of rss.xml, so let's find out how many elements should be related.
    $feed_count = db_query_range('SELECT COUNT(DISTINCT nid) FROM {node_field_data} n WHERE n.promote = 1 AND n.status = 1', 0, \Drupal::config('system.rss')->get('items.limit'))->fetchField();
    return $feed_count > 10 ? 10 : $feed_count;
  }

  /**
   * Updates the feed items.
   *
   * This method simulates a click to
   * admin/config/services/aggregator/update/$fid.
   *
   * @param \Drupal\aggregator\Plugin\Core\Entity\Feed $feed
   *   Feed object representing the feed.
   * @param int|null $expected_count
   *   Expected number of feed items. If omitted no check will happen.
   */
  function updateFeedItems(Feed $feed, $expected_count = NULL) {
    // First, let's ensure we can get to the rss xml.
    $this->drupalGet($feed->url->value);
    $this->assertResponse(200, format_string('!url is reachable.', array('!url' => $feed->url->value)));

    // Attempt to access the update link directly without an access token.
    $this->drupalGet('admin/config/services/aggregator/update/' . $feed->id());
    $this->assertResponse(403);

    // Refresh the feed (simulated link click).
    $this->drupalGet('admin/config/services/aggregator');
    $this->clickLink('Update items');

    // Ensure we have the right number of items.
    $result = db_query('SELECT iid FROM {aggregator_item} WHERE fid = :fid', array(':fid' => $feed->id()));
    $items = array();
    $feed->items = array();
    foreach ($result as $item) {
      $feed->items[] = $item->iid;
    }

    if ($expected_count !== NULL) {
      $feed->item_count = count($feed->items);
      $this->assertEqual($expected_count, $feed->item_count, format_string('Total items in feed equal to the total items in database (!val1 != !val2)', array('!val1' => $expected_count, '!val2' => $feed->item_count)));
    }
  }

  /**
   * Confirms an item removal from a feed.
   *
   * @param  \Drupal\aggregator\Plugin\Core\Entity\Feed $feed
   *   Feed object representing the feed.
   */
  function removeFeedItems(Feed $feed) {
    $this->drupalPost('admin/config/services/aggregator/remove/' . $feed->id(), array(), t('Remove items'));
    $this->assertRaw(t('The news items from %title have been removed.', array('%title' => $feed->label())), 'Feed items removed.');
  }

  /**
   * Adds and removes feed items and ensure that the count is zero.
   *
   * @param  \Drupal\aggregator\Plugin\Core\Entity\Feed $feed
   *   Feed object representing the feed.
   * @param int $expected_count
   *   Expected number of feed items.
   */
  function updateAndRemove(Feed $feed, $expected_count) {
    $this->updateFeedItems($feed, $expected_count);
    $count = db_query('SELECT COUNT(*) FROM {aggregator_item} WHERE fid = :fid', array(':fid' => $feed->id()))->fetchField();
    $this->assertTrue($count);
    $this->removeFeedItems($feed);
    $count = db_query('SELECT COUNT(*) FROM {aggregator_item} WHERE fid = :fid', array(':fid' => $feed->id()))->fetchField();
    $this->assertTrue($count == 0);
  }

  /**
   * Pulls feed categories from {aggregator_category_feed} table.
   *
   * @param \Drupal\aggregator\Plugin\Core\Entity\Feed $feed
   *   Feed object representing the feed.
   */
  function getFeedCategories(Feed $feed) {
    // add the categories to the feed so we can use them
    $result = db_query('SELECT cid FROM {aggregator_category_feed} WHERE fid = :fid', array(':fid' => $feed->id()));

    foreach ($result as $category) {
      $feed->categories[] = $category->cid;
    }
  }

  /**
   * Pulls categories from {aggregator_category} table.
   *
   * @return array
   *   An associative array keyed by category ID and values are set to the
   *   category names.
   */
  function getCategories() {
    $categories = array();
    $result = db_query('SELECT * FROM {aggregator_category}');
    foreach ($result as $category) {
      $categories[$category->cid] = $category;
    }
    return $categories;
  }

  /**
   * Checks whether the feed name and URL are unique.
   *
   * @param $feed_name
   *   String containing the feed name to check.
   * @param $feed_url
   *   String containing the feed url to check.
   *
   * @return
   *   TRUE if feed is unique.
   */
  function uniqueFeed($feed_name, $feed_url) {
    $result = db_query("SELECT COUNT(*) FROM {aggregator_feed} WHERE title = :title AND url = :url", array(':title' => $feed_name, ':url' => $feed_url))->fetchField();
    return (1 == $result);
  }

  /**
   * Creates a valid OPML file from an array of feeds.
   *
   * @param $feeds
   *   An array of feeds.
   *
   * @return
   *   Path to valid OPML file.
   */
  function getValidOpml($feeds) {
    // Properly escape URLs so that XML parsers don't choke on them.
    foreach ($feeds as &$feed) {
      $feed['url'] = htmlspecialchars($feed['url']);
    }
    /**
     * Does not have an XML declaration, must pass the parser.
     */
    $opml = <<<EOF
<opml version="1.0">
  <head></head>
  <body>
    <!-- First feed to be imported. -->
    <outline text="{$feeds[0]['title']}" xmlurl="{$feeds[0]['url']}" />

    <!-- Second feed. Test string delimitation and attribute order. -->
    <outline xmlurl='{$feeds[1]['url']}' text='{$feeds[1]['title']}'/>

    <!-- Test for duplicate URL and title. -->
    <outline xmlurl="{$feeds[0]['url']}" text="Duplicate URL"/>
    <outline xmlurl="http://duplicate.title" text="{$feeds[1]['title']}"/>

    <!-- Test that feeds are only added with required attributes. -->
    <outline text="{$feeds[2]['title']}" />
    <outline xmlurl="{$feeds[2]['url']}" />
  </body>
</opml>
EOF;

    $path = 'public://valid-opml.xml';
    return file_unmanaged_save_data($opml, $path);
  }

  /**
   * Creates an invalid OPML file.
   *
   * @return
   *   Path to invalid OPML file.
   */
  function getInvalidOpml() {
    $opml = <<<EOF
<opml>
  <invalid>
</opml>
EOF;

    $path = 'public://invalid-opml.xml';
    return file_unmanaged_save_data($opml, $path);
  }

  /**
   * Creates a valid but empty OPML file.
   *
   * @return
   *   Path to empty OPML file.
   */
  function getEmptyOpml() {
    $opml = <<<EOF
<?xml version="1.0" encoding="utf-8"?>
<opml version="1.0">
  <head></head>
  <body>
    <outline text="Sample text" />
    <outline text="Sample text" url="Sample URL" />
  </body>
</opml>
EOF;

    $path = 'public://empty-opml.xml';
    return file_unmanaged_save_data($opml, $path);
  }

  function getRSS091Sample() {
    return $GLOBALS['base_url'] . '/' . drupal_get_path('module', 'aggregator') . '/tests/modules/aggregator_test/aggregator_test_rss091.xml';
  }

  function getAtomSample() {
    // The content of this sample ATOM feed is based directly off of the
    // example provided in RFC 4287.
    return $GLOBALS['base_url'] . '/' . drupal_get_path('module', 'aggregator') . '/tests/modules/aggregator_test/aggregator_test_atom.xml';
  }

  function getHtmlEntitiesSample() {
    return $GLOBALS['base_url'] . '/' . drupal_get_path('module', 'aggregator') . '/tests/modules/aggregator_test/aggregator_test_title_entities.xml';
  }

  /**
   * Creates sample article nodes.
   *
   * @param $count
   *   (optional) The number of nodes to generate. Defaults to five.
   */
  function createSampleNodes($count = 5) {
    $langcode = Language::LANGCODE_NOT_SPECIFIED;
    // Post $count article nodes.
    for ($i = 0; $i < $count; $i++) {
      $edit = array();
      $edit['title'] = $this->randomName();
      $edit["body[$langcode][0][value]"] = $this->randomName();
      $this->drupalPost('node/add/article', $edit, t('Save'));
    }
  }

  /**
   * Enable the plugins coming with aggregator_test module.
   */
  function enableTestPlugins() {
    \Drupal::config('aggregator.settings')
      ->set('fetcher', 'aggregator_test_fetcher')
      ->set('parser', 'aggregator_test_parser')
      ->set('processors', array(
        'aggregator_test_processor' => 'aggregator_test_processor',
        'aggregator' => 'aggregator'
      ))
      ->save();
  }
}
