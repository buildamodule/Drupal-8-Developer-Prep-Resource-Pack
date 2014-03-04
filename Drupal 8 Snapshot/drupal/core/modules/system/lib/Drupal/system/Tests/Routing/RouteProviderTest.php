<?php

/**
 * @file
 * Contains Drupal\system\Tests\Routing\RouteProviderTest.
 */

namespace Drupal\system\Tests\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use Drupal\simpletest\UnitTestBase;
use Drupal\Core\Routing\RouteProvider;
use Drupal\Core\Database\Database;
use Drupal\Core\Routing\MatcherDumper;

/**
 * Basic tests for the RouteProvider.
 */
class RouteProviderTest extends UnitTestBase {

  /**
   * A collection of shared fixture data for tests.
   *
   * @var RoutingFixtures
   */
  protected $fixtures;

  public static function getInfo() {
    return array(
      'name' => 'Route Provider tests',
      'description' => 'Confirm that the default route provider is working correctly.',
      'group' => 'Routing',
    );
  }

  function __construct($test_id = NULL) {
    parent::__construct($test_id);

    $this->fixtures = new RoutingFixtures();
  }

  public function tearDown() {
    $this->fixtures->dropTables(Database::getConnection());

    parent::tearDown();
  }

  /**
   * Confirms that the correct candidate outlines are generated.
   */
  public function testCandidateOutlines() {

    $connection = Database::getConnection();
    $provider = new RouteProvider($connection);

    $parts = array('node', '5', 'edit');

    $candidates = $provider->getCandidateOutlines($parts);

    $candidates = array_flip($candidates);

    $this->assertTrue(count($candidates) == 8, 'Correct number of candidates found');
    $this->assertTrue(array_key_exists('/node/5/edit', $candidates), 'First candidate found.');
    $this->assertTrue(array_key_exists('/node/5/%', $candidates), 'Second candidate found.');
    $this->assertTrue(array_key_exists('/node/%/edit', $candidates), 'Third candidate found.');
    $this->assertTrue(array_key_exists('/node/%/%', $candidates), 'Fourth candidate found.');
    $this->assertTrue(array_key_exists('/node/5', $candidates), 'Fifth candidate found.');
    $this->assertTrue(array_key_exists('/node/%', $candidates), 'Sixth candidate found.');
    $this->assertTrue(array_key_exists('/node', $candidates), 'Seventh candidate found.');
    $this->assertTrue(array_key_exists('/', $candidates), 'Eighth candidate found.');
  }

  /**
   * Confirms that we can find routes with the exact incoming path.
   */
  function testExactPathMatch() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($this->fixtures->sampleRouteCollection());
    $dumper->dump();

    $path = '/path/one';

    $request = Request::create($path, 'GET');

    $routes = $provider->getRouteCollectionForRequest($request);

    foreach ($routes as $route) {
      $this->assertEqual($route->getPattern(), $path, 'Found path has correct pattern');
    }
  }

  /**
   * Confirms that we can find routes whose pattern would match the request.
   */
  function testOutlinePathMatch() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($this->fixtures->complexRouteCollection());
    $dumper->dump();

    $path = '/path/1/one';

    $request = Request::create($path, 'GET');

    $routes = $provider->getRouteCollectionForRequest($request);

    // All of the matching paths have the correct pattern.
    foreach ($routes as $route) {
      $this->assertEqual($route->compile()->getPatternOutline(), '/path/%/one', 'Found path has correct pattern');
    }

    $this->assertEqual(count($routes), 2, 'The correct number of routes was found.');
    $this->assertNotNull($routes->get('route_a'), 'The first matching route was found.');
    $this->assertNotNull($routes->get('route_b'), 'The second matching route was not found.');
  }

  /**
   * Confirms that a trailing slash on the request doesn't result in a 404.
   */
  function testOutlinePathMatchTrailingSlash() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($this->fixtures->complexRouteCollection());
    $dumper->dump();

    $path = '/path/1/one/';

    $request = Request::create($path, 'GET');

    $routes = $provider->getRouteCollectionForRequest($request);

    // All of the matching paths have the correct pattern.
    foreach ($routes as $route) {
      $this->assertEqual($route->compile()->getPatternOutline(), '/path/%/one', 'Found path has correct pattern');
    }

    $this->assertEqual(count($routes), 2, 'The correct number of routes was found.');
    $this->assertNotNull($routes->get('route_a'), 'The first matching route was found.');
    $this->assertNotNull($routes->get('route_b'), 'The second matching route was not found.');
  }

  /**
   * Confirms that we can find routes whose pattern would match the request.
   */
  function testOutlinePathMatchDefaults() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $collection = new RouteCollection();
    $collection->add('poink', new Route('/some/path/{value}', array(
      'value' => 'poink',
    )));

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($collection);
    $dumper->dump();

    $path = '/some/path';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRouteCollectionForRequest($request);

      // All of the matching paths have the correct pattern.
      foreach ($routes as $route) {
        $this->assertEqual($route->compile()->getPatternOutline(), '/some/path', 'Found path has correct pattern');
      }

      $this->assertEqual(count($routes), 1, 'The correct number of routes was found.');
      $this->assertNotNull($routes->get('poink'), 'The first matching route was found.');
    }
    catch (ResourceNotFoundException $e) {
      $this->fail('No matching route found with default argument value.');
    }
  }

  /**
   * Confirms that we can find routes whose pattern would match the request.
   */
  function testOutlinePathMatchDefaultsCollision() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $collection = new RouteCollection();
    $collection->add('poink', new Route('/some/path/{value}', array(
      'value' => 'poink',
    )));
    $collection->add('narf', new Route('/some/path/here'));

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($collection);
    $dumper->dump();

    $path = '/some/path';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRouteCollectionForRequest($request);

      // All of the matching paths have the correct pattern.
      foreach ($routes as $route) {
        $this->assertEqual($route->compile()->getPatternOutline(), '/some/path', 'Found path has correct pattern');
      }

      $this->assertEqual(count($routes), 1, 'The correct number of routes was found.');
      $this->assertNotNull($routes->get('poink'), 'The first matching route was found.');
    }
    catch (ResourceNotFoundException $e) {
      $this->fail('No matching route found with default argument value.');
    }
  }

  /**
   * Confirms that we can find routes whose pattern would match the request.
   */
  function testOutlinePathMatchDefaultsCollision2() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $collection = new RouteCollection();
    $collection->add('poink', new Route('/some/path/{value}', array(
      'value' => 'poink',
    )));
    $collection->add('narf', new Route('/some/path/here'));
    $collection->add('eep', new Route('/something/completely/different'));

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($collection);
    $dumper->dump();

    $path = '/some/path/here';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRouteCollectionForRequest($request);
      $routes_array = $routes->all();

      $this->assertEqual(count($routes), 2, 'The correct number of routes was found.');
      $this->assertEqual(array('narf', 'poink'), array_keys($routes_array), 'Ensure the fitness was taken into account.');
      $this->assertNotNull($routes->get('narf'), 'The first matching route was found.');
      $this->assertNotNull($routes->get('poink'), 'The second matching route was found.');
      $this->assertNull($routes->get('eep'), 'Noin-matching route was not found.');
    }
    catch (ResourceNotFoundException $e) {
      $this->fail('No matching route found with default argument value.');
    }
  }

  /**
   * Tests a route with a 0 as value.
   */
  public function testOutlinePathMatchZero() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $collection = new RouteCollection();
    $collection->add('poink', new Route('/some/path/{value}'));

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($collection);
    $dumper->dump();

    $path = '/some/path/0';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRouteCollectionForRequest($request);

      // All of the matching paths have the correct pattern.
      foreach ($routes as $route) {
        $this->assertEqual($route->compile()->getPatternOutline(), '/some/path/%', 'Found path has correct pattern');
      }

      $this->assertEqual(count($routes), 1, 'The correct number of routes was found.');
    }
    catch (ResourceNotFoundException $e) {
      $this->fail('No matchout route found with 0 as argument value');
    }
  }

  /**
   * Confirms that an exception is thrown when no matching path is found.
   */
  function testOutlinePathNoMatch() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($this->fixtures->complexRouteCollection());
    $dumper->dump();

    $path = '/no/such/path';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRoutesByPattern($path);
      $this->assertFalse(count($routes), 'No path found with this pattern.');

      $routes = $provider->getRouteCollectionForRequest($request);
      $this->fail(t('No exception was thrown.'));
    }
    catch (\Exception $e) {
      $this->assertTrue($e instanceof ResourceNotFoundException, 'The correct exception was thrown.');
    }
  }

  /**
   * Confirms that _system_path attribute overrides request path.
   */
  function testSystemPathMatch() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($this->fixtures->sampleRouteCollection());
    $dumper->dump();

    $request = Request::create('/path/one', 'GET');
    $request->attributes->set('_system_path', 'path/two');

    $routes_by_pattern = $provider->getRoutesByPattern('/path/two');
    $routes = $provider->getRouteCollectionForRequest($request);
    $this->assertEqual(array_keys($routes_by_pattern->all()), array_keys($routes->all()), 'Ensure the expected routes are found.');

    foreach ($routes as $route) {
      $this->assertEqual($route->getPattern(), '/path/two', 'Found path has correct pattern');
    }
  }

  /**
   * Test RouteProvider::getRouteByName() and RouteProvider::getRoutesByNames().
   */
  protected function testRouteByName() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($this->fixtures->sampleRouteCollection());
    $dumper->dump();

    $route = $provider->getRouteByName('route_a');
    $this->assertEqual($route->getPattern(), '/path/one', 'The right route pattern was found.');
    $this->assertEqual($route->getRequirement('_method'), 'GET', 'The right route method was found.');
    $route = $provider->getRouteByName('route_b');
    $this->assertEqual($route->getPattern(), '/path/one', 'The right route pattern was found.');
    $this->assertEqual($route->getRequirement('_method'), 'PUT', 'The right route method was found.');

    $exception_thrown = FALSE;
    try {
      $provider->getRouteByName('invalid_name');
    }
    catch (RouteNotFoundException $e) {
      $exception_thrown = TRUE;
    }
    $this->assertTrue($exception_thrown, 'Random route was not found.');

    $routes = $provider->getRoutesByNames(array('route_c', 'route_d', $this->randomName()));
    $this->assertEqual(count($routes), 2, 'Only two valid routes found.');
    $this->assertEqual($routes['route_c']->getPattern(), '/path/two');
    $this->assertEqual($routes['route_d']->getPattern(), '/path/three');
  }

}
