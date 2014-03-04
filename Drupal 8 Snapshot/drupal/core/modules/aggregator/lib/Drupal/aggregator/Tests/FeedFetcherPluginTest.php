<?php

/**
 * @file
 * Contains \Drupal\aggregator\Tests\FeedFetcherPluginTest.
 */

namespace Drupal\aggregator\Tests;

/**
 * Tests feed fetching in the Aggregator module.
 *
 * @see \Drupal\aggregator_test\Plugin\aggregator\fetcher\TestFetcher.
 */
class FeedFetcherPluginTest extends AggregatorTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Feed fetcher plugins',
      'description' => 'Test the fetcher plugins functionality and discoverability.',
      'group' => 'Aggregator',
    );
  }

  public function setUp() {
    parent::setUp();
    // Enable test plugins.
    $this->enableTestPlugins();
    // Create some nodes.
    $this->createSampleNodes();
  }

  /**
   * Test fetching functionality.
   */
  public function testfetch() {
    // Create feed with local url.
    $feed = $this->createFeed();
    $this->updateFeedItems($feed);
    $this->assertFalse(empty($feed->items));

    // Remove items and restore checked property to 0.
    $this->removeFeedItems($feed);
    // Change its name and try again.
    $feed->title->value = 'Do not fetch';
    $feed->save();
    $this->updateFeedItems($feed);
    // Fetch should fail due to feed name.
    $this->assertTrue(empty($feed->items));
  }
}
