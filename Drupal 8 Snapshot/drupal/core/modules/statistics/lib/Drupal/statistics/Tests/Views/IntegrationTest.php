<?php

/**
 * @file
 * Contains \Drupal\statistics\Tests\Views\IntegrationTest.
 */

namespace Drupal\statistics\Tests\Views;

use Drupal\views\Tests\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests basic integration of views data from the statistics module.
 *
 * @see
 */
class IntegrationTest extends ViewTestBase {


  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('statistics', 'statistics_test_views');

  /**
   * Stores the user object that accesses the page.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * Stores the node object which is used by the test.
   *
   * @var \Drupal\node\Plugin\Core\Entity\Node
   */
  protected $node;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_statistics_integration');

  public static function getInfo() {
    return array(
      'name' => 'Statistics: Integration tests',
      'description' => 'Tests basic integration of views data from the statistics module.',
      'group' => 'Views module integration',
    );
  }

  protected function setUp() {
    parent::setUp();

    ViewTestData::importTestViews(get_class($this), array('statistics_test_views'));

    // Create a new user for viewing nodes.
    $this->webUser = $this->drupalCreateUser(array('access content'));

    $this->node = $this->drupalCreateNode(array('type' => 'page'));

    // Enable access logging.
    $this->container->get('config.factory')->get('statistics.settings')
      ->set('access_log.enabled', 1)
      ->set('count_content_views', 1)
      ->save();

    $this->drupalLogin($this->webUser);
  }

  /**
   * Tests the integration of the {node_counter} table in views.
   */
  public function testNodeCounterIntegration() {
    $this->drupalGet('node/' . $this->node->id());
    // Manually calling statistics.php, simulating ajax behavior.
    // @see \Drupal\statistics\Tests\StatisticsLoggingTest::testLogging().
    global $base_url;
    $stats_path = $base_url . '/' . drupal_get_path('module', 'statistics'). '/statistics.php';
    $client = \Drupal::httpClient();
    $client->setConfig(array('curl.options' => array(CURLOPT_TIMEOUT => 10)));
    $client->post($stats_path, array(), array('nid' => $this->node->id()))->send();
    $this->drupalGet('test_statistics_integration');

    $expected = statistics_get($this->node->id());
    // Convert the timestamp to year, to match the expected output of the date
    // handler.
    $expected['timestamp'] = date('Y', $expected['timestamp']);

    foreach ($expected as $field => $value) {
      $xpath = "//div[contains(@class, views-field-$field)]/span[@class = 'field-content']";
      $this->assertFieldByXpath($xpath, $value, "The $field output matches the expected.");
    }
  }

}
