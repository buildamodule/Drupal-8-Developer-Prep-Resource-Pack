<?php

/**
 * @file
 * Contains Drupal\Core\Routing\RouteProvider.
 */

namespace Drupal\Core\Routing;

use Drupal\Component\Utility\String;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use \Drupal\Core\Database\Connection;

/**
 * A Route Provider front-end for all Drupal-stored routes.
 */
class RouteProvider implements RouteProviderInterface {

  /**
   * The database connection from which to read route information.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The name of the SQL table from which to read the routes.
   *
   * @var string
   */
  protected $tableName;

  /**
   * A cache of already-loaded routes, keyed by route name.
   *
   * @var array
   */
  protected $routes = array();

  /**
   * Constructs a new PathMatcher.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A database connection object.
   * @param string $table
   *   The table in the database to use for matching.
   */
  public function __construct(Connection $connection, $table = 'router') {
    $this->connection = $connection;
    $this->tableName = $table;
  }

  /**
   * Finds routes that may potentially match the request.
   *
   * This may return a mixed list of class instances, but all routes returned
   * must extend the core symfony route. The classes may also implement
   * RouteObjectInterface to link to a content document.
   *
   * This method may not throw an exception based on implementation specific
   * restrictions on the url. That case is considered a not found - returning
   * an empty array. Exceptions are only used to abort the whole request in
   * case something is seriously broken, like the storage backend being down.
   *
   * Note that implementations may not implement an optimal matching
   * algorithm, simply a reasonable first pass.  That allows for potentially
   * very large route sets to be filtered down to likely candidates, which
   * may then be filtered in memory more completely.
   *
   * @param Request $request A request against which to match.
   *
   * @return \Symfony\Component\Routing\RouteCollection with all urls that
   *      could potentially match $request. Empty collection if nothing can
   *      match.
   *
   * @todo Should this method's found routes also be included in the cache?
   */
  public function getRouteCollectionForRequest(Request $request) {

    // The '_system_path' has language prefix stripped and path alias resolved,
    // whereas getPathInfo() returns the requested path. In Drupal, the request
    // always contains a system_path attribute, but this component may get
    // adopted by non-Drupal projects. Some unit tests also skip initializing
    // '_system_path'.
    // @todo Consider abstracting this to a separate object.
    if ($request->attributes->has('_system_path')) {
      // _system_path never has leading or trailing slashes.
      $path = '/' . $request->attributes->get('_system_path');
    }
    else {
      // getPathInfo() always has leading slash, and might or might not have a
      // trailing slash.
      $path = rtrim($request->getPathInfo(), '/');
    }

    $collection = $this->getRoutesByPath($path);

    if (!count($collection)) {
      throw new ResourceNotFoundException(String::format("The route for '@path' could not be found", array('@path' => $path)));
    }

    return $collection;
  }

  /**
   * Find the route using the provided route name (and parameters).
   *
   * @param string $name
   *   The route name to fetch
   * @param array $parameters
   *   The parameters as they are passed to the UrlGeneratorInterface::generate
   *   call.
   *
   * @return \Symfony\Component\Routing\Route
   *   The found route.
   *
   * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
   *   Thrown if there is no route with that name in this repository.
   */
  public function getRouteByName($name, $parameters = array()) {
    $routes = $this->getRoutesByNames(array($name), $parameters);
    if (empty($routes)) {
      throw new RouteNotFoundException(sprintf('Route "%s" does not exist.', $name));
    }

    return reset($routes);
  }

  /**
   * Find many routes by their names using the provided list of names.
   *
   * Note that this method may not throw an exception if some of the routes
   * are not found. It will just return the list of those routes it found.
   *
   * This method exists in order to allow performance optimizations. The
   * simple implementation could be to just repeatedly call
   * $this->getRouteByName().
   *
   * @param array $names
   *   The list of names to retrieve.
   * @param array $parameters
   *   The parameters as they are passed to the UrlGeneratorInterface::generate
   *   call. (Only one array, not one for each entry in $names).
   *
   * @return \Symfony\Component\Routing\Route[]
   *   Iterable thing with the keys the names of the $names argument.
   */
  public function getRoutesByNames($names, $parameters = array()) {

    if (empty($names)) {
      throw new \InvalidArgumentException('You must specify the route names to load');
    }

    $routes_to_load = array_diff($names, array_keys($this->routes));

    if ($routes_to_load) {
      $result = $this->connection->query('SELECT name, route FROM {' . $this->connection->escapeTable($this->tableName) . '} WHERE name IN (:names)', array(':names' => $routes_to_load));
      $routes = $result->fetchAllKeyed();

      foreach ($routes as $name => $route) {
        $this->routes[$name] = unserialize($route);
      }
    }

    return array_intersect_key($this->routes, array_flip($names));
  }

  /**
   * Returns an array of path pattern outlines that could match the path parts.
   *
   * @param array $parts
   *   The parts of the path for which we want candidates.
   *
   * @return array
   *   An array of outlines that could match the specified path parts.
   */
  public function getCandidateOutlines(array $parts) {
    $number_parts = count($parts);
    $ancestors = array();
    $length =  $number_parts - 1;
    $end = (1 << $number_parts) - 1;

    // The highest possible mask is a 1 bit for every part of the path. We will
    // check every value down from there to generate a possible outline.
    $masks = range($end, 0);

    // Only examine patterns that actually exist as router items (the masks).
    foreach ($masks as $i) {
      if ($i > $end) {
        // Only look at masks that are not longer than the path of interest.
        continue;
      }
      elseif ($i < (1 << $length)) {
        // We have exhausted the masks of a given length, so decrease the length.
        --$length;
      }
      $current = '';
      for ($j = $length; $j >= 0; $j--) {
        // Check the bit on the $j offset.
        if ($i & (1 << $j)) {
          // Bit one means the original value.
          $current .= $parts[$length - $j];
        }
        else {
          // Bit zero means means wildcard.
          $current .= '%';
        }
        // Unless we are at offset 0, add a slash.
        if ($j) {
          $current .= '/';
        }
      }
      $ancestors[] = '/' . $current;
    }
    return $ancestors;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutesByPattern($pattern) {
    $path = RouteCompiler::getPatternOutline($pattern);

    return $this->getRoutesByPath($path);
  }

  /**
   * Get all routes which match a certain pattern.
   *
   * @param string $path
   *   The route pattern to search for (contains % as placeholders).
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   Returns a route collection of matching routes.
   */
  protected function getRoutesByPath($path) {
    // Filter out each empty value, though allow '0' and 0, which would be
    // filtered out by empty().
    $parts = array_slice(array_filter(explode('/', $path), function($value) {
      return $value !== NULL && $value !== '';
    }), 0, MatcherDumper::MAX_PARTS);

    $ancestors = $this->getCandidateOutlines($parts);

    $routes = $this->connection->query("SELECT name, route FROM {" . $this->connection->escapeTable($this->tableName) . "} WHERE pattern_outline IN (:patterns) ORDER BY fit DESC", array(
      ':patterns' => $ancestors,
    ))
      ->fetchAllKeyed();

    $collection = new RouteCollection();
    foreach ($routes as $name => $route) {
      $route = unserialize($route);
      if (preg_match($route->compile()->getRegex(), $path, $matches)) {
        $collection->add($name, $route);
      }
    }

    return $collection;
  }

}
