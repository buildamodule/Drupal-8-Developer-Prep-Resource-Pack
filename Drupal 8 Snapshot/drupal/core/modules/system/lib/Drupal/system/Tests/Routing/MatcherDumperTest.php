<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Routing\UrlMatcherDumperTest.
 */

namespace Drupal\system\Tests\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

use Drupal\simpletest\UnitTestBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Routing\MatcherDumper;

/**
 * Basic tests for the UrlMatcherDumper.
 */
class MatcherDumperTest extends UnitTestBase {

  /**
   * A collection of shared fixture data for tests.
   *
   * @var RoutingFixtures
   */
  protected $fixtures;

  public static function getInfo() {
    return array(
      'name' => 'Dumper tests',
      'description' => 'Confirm that the matcher dumper is functioning properly.',
      'group' => 'Routing',
    );
  }

  function __construct($test_id = NULL) {
    parent::__construct($test_id);

    $this->fixtures = new RoutingFixtures();
  }

  function setUp() {
    parent::setUp();
  }

  /**
   * Confirms that the dumper can be instantiated successfuly.
   */
  function testCreate() {
    $connection = Database::getConnection();
    $dumper= new MatcherDumper($connection);

    $class_name = 'Drupal\Core\Routing\MatcherDumper';
    $this->assertTrue($dumper instanceof $class_name, 'Dumper created successfully');
  }

  /**
   * Confirms that we can add routes to the dumper.
   */
  function testAddRoutes() {
    $connection = Database::getConnection();
    $dumper= new MatcherDumper($connection);

    $route = new Route('test');
    $collection = new RouteCollection();
    $collection->add('test_route', $route);

    $dumper->addRoutes($collection);

    $dumper_routes = $dumper->getRoutes()->all();
    $collection_routes = $collection->all();

    foreach ($dumper_routes as $name => $route) {
      $this->assertEqual($route->getPattern(), $collection_routes[$name]->getPattern(), 'Routes match');
    }
  }

  /**
   * Confirms that we can add routes to the dumper when it already has some.
   */
  function testAddAdditionalRoutes() {
    $connection = Database::getConnection();
    $dumper= new MatcherDumper($connection);

    $route = new Route('test');
    $collection = new RouteCollection();
    $collection->add('test_route', $route);
    $dumper->addRoutes($collection);

    $route = new Route('test2');
    $collection2 = new RouteCollection();
    $collection2->add('test_route2', $route);
    $dumper->addRoutes($collection2);

    // Merge the two collections together so we can test them.
    $collection->addCollection(clone $collection2);

    $dumper_routes = $dumper->getRoutes()->all();
    $collection_routes = $collection->all();

    $success = TRUE;
    foreach ($collection_routes as $name => $route) {
      if (empty($dumper_routes[$name])) {
        $success = FALSE;
        $this->fail(t('Not all routes found in the dumper.'));
      }
    }

    if ($success) {
      $this->pass('All routes found in the dumper.');
    }
  }

  /**
   * Confirm that we can dump a route collection to the database.
   */
  public function testDump() {
    $connection = Database::getConnection();
    $dumper= new MatcherDumper($connection, 'test_routes');

    $route = new Route('/test/{my}/path');
    $route->setOption('compiler_class', 'Drupal\Core\Routing\RouteCompiler');
    $collection = new RouteCollection();
    $collection->add('test_route', $route);

    $dumper->addRoutes($collection);

    $this->fixtures->createTables($connection);

    $dumper->dump(array('route_set' => 'test'));

    $record = $connection->query("SELECT * FROM {test_routes} WHERE name= :name", array(':name' => 'test_route'))->fetchObject();

    $loaded_route = unserialize($record->route);

    $this->assertEqual($record->name, 'test_route', 'Dumped route has correct name.');
    $this->assertEqual($record->pattern, '/test/{my}/path', 'Dumped route has correct pattern.');
    $this->assertEqual($record->pattern_outline, '/test/%/path', 'Dumped route has correct pattern outline.');
    $this->assertEqual($record->fit, 5 /* 101 in binary */, 'Dumped route has correct fit.');
    $this->assertTrue($loaded_route instanceof Route, 'Route object retrieved successfully.');

  }
}
