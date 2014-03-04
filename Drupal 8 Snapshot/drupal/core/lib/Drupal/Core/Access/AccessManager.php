<?php

/**
 * @file
 * Contains Drupal\Core\Access\AccessManager.
 */

namespace Drupal\Core\Access;

use Drupal\Core\ParamConverter\ParamConverterManager;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;

/**
 * Attaches access check services to routes and runs them on request.
 *
 * @see \Drupal\Tests\Core\Access\AccessManagerTest
 */
class AccessManager extends ContainerAware {

  /**
   * Array of registered access check service ids.
   *
   * @var array
   */
  protected $checkIds = array();

  /**
   * Array of access check objects keyed by service id.
   *
   * @var array
   */
  protected $checks;

  /**
   * An array to map static requirement keys to service IDs.
   *
   * @var array
   */
  protected $staticRequirementMap;

  /**
   * An array to map dynamic requirement keys to service IDs.
   *
   * @var array
   */
  protected $dynamicRequirementMap;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The url generator.
   *
   * @var \Symfony\Component\Routing\Generator\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The paramconverter manager.
   *
   * @var \Drupal\Core\ParamConverter\ParamConverterManager
   */
  protected $paramConverterManager;

  /**
   * A request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a AccessManager instance.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Symfony\Component\Routing\Generator\UrlGeneratorInterface $url_generator
   *   The url generator.
   * @param \Drupal\Core\ParamConverter\ParamConverterManager $paramconverter_manager
   *   The param converter manager.
   */
  public function __construct(RouteProviderInterface $route_provider, UrlGeneratorInterface $url_generator, ParamConverterManager $paramconverter_manager) {
    $this->routeProvider = $route_provider;
    $this->urlGenerator = $url_generator;
    $this->paramConverterManager = $paramconverter_manager;
  }

  /**
   * Sets the request object to use.
   *
   * This is used by the RouterListener to make additional request attributes
   * available.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  public function setRequest(Request $request) {
    $this->request = $request;
  }

  /**
   * Registers a new AccessCheck by service ID.
   *
   * @param string $service_id
   *   The ID of the service in the Container that provides a check.
   */
  public function addCheckService($service_id) {
    $this->checkIds[] = $service_id;
  }

  /**
   * For each route, saves a list of applicable access checks to the route.
   *
   * @param \Symfony\Component\Routing\RouteCollection $routes
   *   A collection of routes to apply checks to.
   */
  public function setChecks(RouteCollection $routes) {
    $this->loadAccessRequirementMap();
    foreach ($routes as $route) {
      if ($checks = $this->applies($route)) {
        $route->setOption('_access_checks', $checks);
      }
    }
  }

  /**
   * Determine which registered access checks apply to a route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to get list of access checks for.
   *
   * @return array
   *   An array of service ids for the access checks that apply to passed
   *   route.
   */
  protected function applies(Route $route) {
    $checks = array();

    // Iterate through map requirements from appliesTo() on access checkers.
    // Only iterate through all checkIds if this is not used.
    foreach ($route->getRequirements() as $key => $value) {
      if (isset($this->staticRequirementMap[$key])) {
        foreach ($this->staticRequirementMap[$key] as $service_id) {
          $checks[] = $service_id;
        }
      }
      // This means appliesTo() method was empty. Iterate through all checkers.
      else {
        foreach ($this->dynamicRequirementMap as $service_id) {
          if ($this->checks[$service_id]->applies($route)) {
            $checks[] = $service_id;
          }
        }
      }
    }

    return $checks;
  }

  /**
   * Checks a named route with parameters against applicable access check services.
   *
   * Determines whether the route is accessible or not.
   *
   * @param string $route_name
   *   The route to check access to.
   * @param array $parameters
   *   Optional array of values to substitute into the route path patern.
   * @param \Symfony\Component\HttpFoundation\Request $route_request
   *   Optional incoming request object. If not provided, one will be built
   *   using the route information and the current request from the container.
   *
   * @return bool
   *   Returns TRUE if the user has access to the route, otherwise FALSE.
   */
  public function checkNamedRoute($route_name, array $parameters = array(), Request $route_request = NULL) {
    try {
      $route = $this->routeProvider->getRouteByName($route_name, $parameters);
      if (empty($route_request)) {
        // Create a request and copy the account from the current request.
        $route_request = Request::create($this->urlGenerator->generate($route_name, $parameters));
        $defaults = $parameters;
        $defaults['_account'] = $this->request->attributes->get('_account');
        $defaults[RouteObjectInterface::ROUTE_OBJECT] = $route;
        $route_request->attributes->add($this->paramConverterManager->enhance($defaults, $route_request));
      }
      return $this->check($route, $route_request);
    }
    catch (RouteNotFoundException $e) {
      return FALSE;
    }
    catch (NotFoundHttpException $e) {
      return FALSE;
    }
  }

  /**
   * Checks a route against applicable access check services.
   *
   * Determines whether the route is accessible or not.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check access to.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   *
   * @return bool
   *   Returns TRUE if the user has access to the route, otherwise FALSE.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If any access check denies access or none explicitly approve.
   */
  public function check(Route $route, Request $request) {
    $checks = $route->getOption('_access_checks') ?: array();

    $conjunction = $route->getOption('_access_mode') ?: 'ANY';

    if ($conjunction == 'ALL') {
      return $this->checkAll($checks, $route, $request);
    }
    else {
      return $this->checkAny($checks, $route, $request);
    }
  }

  /**
   * Checks access so that every checker should allow access.
   *
   * @param array $checks
   *   Contains the list of checks on the route definition.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check access to.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   *
   * @return bool
   *  Returns TRUE if the user has access to the route, else FALSE.
   */
  protected function checkAll(array $checks, Route $route, Request $request) {
    $access = FALSE;

    foreach ($checks as $service_id) {
      if (empty($this->checks[$service_id])) {
        $this->loadCheck($service_id);
      }

      $service_access = $this->checks[$service_id]->access($route, $request);
      if ($service_access === AccessInterface::ALLOW) {
        $access = TRUE;
      }
      else {
        // On both KILL and DENY stop.
        $access = FALSE;
        break;
      }
    }

    return $access;
  }

  /**
   * Checks access so that at least one checker should allow access.
   *
   * @param array $checks
   *   Contains the list of checks on the route definition.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check access to.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   *
   * @return bool
   *  Returns TRUE if the user has access to the route, else FALSE.
   */
  protected function checkAny(array $checks, $route, $request) {
    // No checks == deny by default.
    $access = FALSE;

    foreach ($checks as $service_id) {
      if (empty($this->checks[$service_id])) {
        $this->loadCheck($service_id);
      }

      $service_access = $this->checks[$service_id]->access($route, $request);
      if ($service_access === AccessInterface::ALLOW) {
        $access = TRUE;
      }
      if ($service_access === AccessInterface::KILL) {
        return FALSE;
      }
    }

    return $access;
  }

  /**
   * Lazy-loads access check services.
   *
   * @param string $service_id
   *   The service id of the access check service to load.
   */
  protected function loadCheck($service_id) {
    if (!in_array($service_id, $this->checkIds)) {
      throw new \InvalidArgumentException(sprintf('No check has been registered for %s', $service_id));
    }

    $this->checks[$service_id] = $this->container->get($service_id);
  }

  /**
   * Compiles a mapping of requirement keys to access checker service IDs.
   */
  public function loadAccessRequirementMap() {
    if (isset($this->staticRequirementMap, $this->dynamicRequirementMap)) {
      return;
    }

    // Set them here, so we can use the isset() check above.
    $this->staticRequirementMap = array();
    $this->dynamicRequirementMap = array();

    foreach ($this->checkIds as $service_id) {
      if (empty($this->checks[$service_id])) {
        $this->loadCheck($service_id);
      }

      // Empty arrays will not register anything.
      if (is_subclass_of($this->checks[$service_id], 'Drupal\Core\Access\StaticAccessCheckInterface')) {
        foreach ((array) $this->checks[$service_id]->appliesTo() as $key) {
          $this->staticRequirementMap[$key][] = $service_id;
        }
      }
      // Add the service ID to a the regular that will be iterated over.
      else {
        $this->dynamicRequirementMap[] = $service_id;
      }
    }
  }

}
