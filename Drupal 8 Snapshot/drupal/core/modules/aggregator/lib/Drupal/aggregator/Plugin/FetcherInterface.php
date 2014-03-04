<?php

/**
 * @file
 * Contains \Drupal\aggregator\Plugin\FetcherInterface.
 */

namespace Drupal\aggregator\Plugin;

use Drupal\aggregator\Plugin\Core\Entity\Feed;

/**
 * Defines an interface for aggregator fetcher implementations.
 *
 * A fetcher downloads feed data to a Drupal site. The fetcher is called at the
 * first of the three aggregation stages: first, data is downloaded by the
 * active fetcher; second, it is converted to a common format by the active
 * parser; and finally, it is passed to all active processors, which manipulate
 * or store the data.
 */
interface FetcherInterface {

  /**
   * Downloads feed data.
   *
   * @param \Drupal\aggregator\Plugin\Core\Entity\Feed $feed
   *   A feed object representing the resource to be downloaded.
   *   $feed->url->value contains the link to the feed.
   *   Download the data at the URL and expose it
   *   to other modules by attaching it to $feed->source_string.
   *
   * @return
   *   TRUE if fetching was successful, FALSE otherwise.
   */
  public function fetch(Feed $feed);

}
